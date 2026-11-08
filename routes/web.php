<?php
use App\QueueModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('dashboard', ['operator'=>false]));
Route::get('/operations', fn() => view('dashboard', ['operator'=>true]));
Route::get('/operations/checkpoints/{code}', function (string $code) {
    if (!session('operator')) {
        return redirect('/operations');
    }
    abort_unless(DB::table('checkpoint')->where('code',$code)->where('code','<>','LSC')->exists(),404);
    return response()->view('dashboard',['operator'=>true,'detailCode'=>$code])->header('Cache-Control','no-store');
});
Route::post('/api/login', function (Request $request) {
    $credentials = $request->validate(['username'=>'required|string|max:80','password'=>'required|string|max:256']);
    $username = (string) config('borderflow.operator_username');
    $password = $credentials['password'];
    $expected = config('borderflow.operator_password');
    abort_unless(is_string($expected) && strlen($expected) >= 12 && hash_equals($username, $credentials['username']) && hash_equals($expected, $password), 401, '登入失敗');
    $request->session()->regenerate();
    $request->session()->put('operator', true);
    return ['csrf'=>csrf_token()];
})->middleware('throttle:8,1');
Route::post('/api/logout', function (Request $request) {
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return ['csrf'=>csrf_token()];
});
Route::get('/api/checkpoints', function (Request $request) {
    $input = $request->validate(['direction'=>'required|in:Arrival,Departure','audience'=>'required|in:R,V']);
    $audience = $input['audience'];
    try {
        $official = Cache::remember('waiting-'.$audience, 300, function () use ($audience) {
            $response = Http::timeout(4)->get("https://secure1.info.gov.hk/immd/mobileapps/2bb9ae17/data/CPQueueTime$audience.json")->throw();
            $data = $response->json();
            if (!is_array($data) || !isset($data['LWS'])) {
                throw new RuntimeException('Unexpected waiting feed');
            }
            return ['retrieved'=>now()->toIso8601String(),'data'=>$data];
        });
    } catch (Throwable) {
        $official = null;
    }
    $field = $input['direction'] === 'Arrival' ? 'arrQueue' : 'depQueue';
    $points = DB::table('checkpoint')->whereNotNull('transport')->where('code','<>','LSC')->orderBy('code')->get();
    foreach ($points as $point) {
        unset($point->lanes);
        $point->status = $official['data'][$point->code][$field] ?? null;
    }
    return ['retrieved'=>$official['retrieved'] ?? null,'points'=>$points];
});
Route::get('/api/history', function (Request $request) {
    $data = $request->validate(['code'=>'required|exists:checkpoint,code|not_in:LSC','direction'=>'required|in:Arrival,Departure']);
    return DB::table('daily_traffic')->where('checkpoint_code',$data['code'])->where('direction',$data['direction'])
        ->orderByDesc('day')->limit(28)->get(['day','total','source'])->reverse()->values();
});

Route::middleware(\App\OperatorOnly::class)->prefix('/api/ops')->group(function () {
    Route::get('/travellers', function (Request $request) {
        $query = $request->validate(['q'=>'nullable|string|max:60'])['q'] ?? '';
        return DB::table('traveller')->where(function ($builder) use ($query) {
            $builder->where('demo_document','like',"%$query%")->orWhere('display_name','like',"%$query%");
        })->orderBy('id')->limit(50)->get(['id','display_name','demo_document']);
    });
    Route::get('/travellers/{id}', function (int $id) {
        $traveller = DB::table('traveller')->where('id',$id)->first();
        abort_unless($traveller,404);
        $crossings = DB::table('crossing')->where('traveller_id',$id)->where('checkpoint_code','<>','LSC');
        return ['profile'=>$traveller,'count'=>(clone $crossings)->count(),
            'crossings'=>$crossings->join('checkpoint','checkpoint.code','=','crossing.checkpoint_code')->where('checkpoint.code','<>','LSC')
                ->orderByDesc('occurred_at')->limit(100)->get(['checkpoint.name','checkpoint.chinese','occurred_at','direction'])];
    })->whereNumber('id');
    Route::get('/checkpoints', fn() => DB::table('checkpoint')->where('code','<>','LSC')->orderBy('name')->get(['code','name','chinese']));
    Route::get('/checkpoints/{code}/minute', function (string $code) {
        $point = DB::table('checkpoint')->where('code',$code)->where('code','<>','LSC')->first();
        abort_unless($point,404);
        $minute = now()->startOfMinute();
        $stamp = $minute->format('Y-m-d H:i');
        return ['minute'=>$stamp,'source'=>'simulation',
            'arrivals'=>crc32($code.$stamp.'arrival') % ($point->lanes * 4 + 1),
            'departures'=>crc32($code.$stamp.'departure') % ($point->lanes * 4 + 1)];
    });
    Route::get('/checkpoints/{code}/details', function (string $code) {
        $point = DB::table('checkpoint')->where('code',$code)->where('code','<>','LSC')->first(['code','name','chinese']);
        abort_unless($point,404);
        $today = now()->startOfDay();
        $history = DB::table('daily_traffic')->where('checkpoint_code',$code)->groupBy('day')
            ->selectRaw("day, MAX(IF(direction='Arrival',total,NULL)) AS arrivals, MAX(IF(direction='Departure',total,NULL)) AS departures")
            ->orderByDesc('day')->limit(30)->get();
        $byDay = $history->keyBy('day');
        $lastSeven = [];
        for ($days=6; $days>=0; $days--) {
            $day = $today->copy()->subDays($days)->toDateString();
            $lastSeven[] = $byDay->get($day) ?? ['day'=>$day,'arrivals'=>null,'departures'=>null];
        }
        return ['point'=>$point,'date'=>$today->toDateString(),'today'=>$byDay->get($today->toDateString()),
            'history'=>$history,'lastSeven'=>$lastSeven];
    });
    Route::get('/database-lab', function () {
        $day = DB::table('daily_traffic')->max('day');
        if (!$day) {
            return ['day'=>null,'plans'=>[]];
        }
        $plans = [];
        foreach (['無日期索引'=>'IGNORE INDEX (traffic_day)', '使用日期索引'=>'FORCE INDEX (traffic_day)'] as $label=>$hint) {
            $sql = "SELECT SUM(total) FROM daily_traffic $hint WHERE day = ? AND direction = 'Arrival'";
            $plan = DB::select('EXPLAIN ANALYZE '.$sql,[$day]);
            $plans[] = ['label'=>$label,'sql'=>$sql,'plan'=>array_values((array) $plan[0])[0]];
        }
        return ['day'=>$day,'plans'=>$plans];
    });
    Route::get('/crossings', function (Request $request) {
        $input = $request->validate(['code'=>'nullable|exists:checkpoint,code','day'=>'nullable|date_format:Y-m-d',
            'direction'=>'nullable|in:Arrival,Departure']);
        $query = DB::table('crossing as x')->join('traveller as t','t.id','=','x.traveller_id')
            ->join('checkpoint as c','c.code','=','x.checkpoint_code')->where('c.code','<>','LSC');
        if (!empty($input['code'])) {
            $query->where('c.code',$input['code']);
        }
        if (!empty($input['day'])) {
            $query->where('x.occurred_at','>=',$input['day'])->where('x.occurred_at','<',\Illuminate\Support\Carbon::parse($input['day'])->addDay()->toDateString());
        }
        if (!empty($input['direction'])) {
            $query->where('x.direction',$input['direction']);
        }
        return $query->orderByDesc('x.occurred_at')->limit(100)->get(['t.id','t.display_name','t.demo_document','c.name','c.chinese','x.occurred_at','x.direction']);
    });
    Route::get('/statistics', function () {
        $day = DB::table('daily_traffic')->max('day');
        return ['day'=>$day, 'rows'=>DB::table('daily_traffic as t')->join('checkpoint as c','c.code','=','t.checkpoint_code')
            ->where('t.day',$day)->where('c.code','<>','LSC')->groupBy('c.code','c.name','c.chinese')
            ->selectRaw("c.code, c.name, c.chinese, SUM(IF(t.direction='Arrival',t.total,0)) AS arrivals, SUM(IF(t.direction='Departure',t.total,0)) AS departures")
            ->orderByDesc('arrivals')->get()];
    });
    Route::post('/simulations', function (Request $request) {
        $data = $request->validate(['code'=>'required|exists:checkpoint,code|not_in:LSC','base'=>'required|integer|min:0|max:1500',
            'increase'=>'required|integer|min:0|max:1500','ramp'=>'required|integer|min:1|max:60',
            'lanes'=>'required|integer|min:1|max:100','initial'=>'required|integer|min:0|max:10000']);
        $series = QueueModel::run($data['base'],$data['increase'],$data['ramp'],$data['lanes'],$data['initial']);
        $baseline = QueueModel::run($data['base'],0,$data['ramp'],$data['lanes'],$data['initial']);
        $result = ['scenario'=>$series,'baseline'=>$baseline];
        $result['id'] = DB::table('simulation')->insertGetId(['checkpoint_code'=>$data['code'],
            'parameters'=>json_encode($data),'results'=>json_encode($result)]);
        return $result;
    });
    Route::get('/simulations', fn() => DB::table('simulation')->where('checkpoint_code','<>','LSC')->orderByDesc('id')->limit(15)->get());
    Route::get('/slots', function () {
        // Idempotent fixture setup for the next twelve dates.
        return DB::transaction(function () {
            for ($offset=2; $offset<=12; $offset++) {
                $day = now()->addDays($offset)->toDateString();
                $policy = DB::table('booking_policy')->where('effective_on','<=',$day)->orderByDesc('effective_on')->first();
                if ($policy?->tue_wed_free && in_array(now()->addDays($offset)->dayOfWeek,[2,3],true)) {
                    continue;
                }
                DB::table('quota_day')->insertOrIgnore(['day'=>$day,'capacity'=>8]);
                foreach (['09:00:00','14:00:00'] as $opens) {
                    DB::table('quota_slot')->insertOrIgnore(['day'=>$day,'opens'=>$opens,'capacity'=>5]);
                }
            }
            return DB::table('quota_slot as s')->join('quota_day as d','d.day','=','s.day')
                ->where('s.day','>',now()->toDateString())->orderBy('s.day')->orderBy('s.opens')
                ->selectRaw('s.*, LEAST(s.capacity-s.reserved,d.capacity-d.reserved) AS remaining')->get();
        }, 3);
    });
    Route::get('/bookings', fn() => DB::table('booking as b')->join('quota_slot as s','b.slot_id','=','s.id')
        ->orderByDesc('b.id')->limit(50)->get(['b.*','s.day','s.opens']));
    Route::post('/bookings', function (Request $request) {
        $data = $request->validate(['slot'=>'required|integer|exists:quota_slot,id','vehicle'=>['required','regex:/^DEMO-[A-Z0-9]{1,16}$/']]);
        try {
            $id = DB::transaction(function () use ($data) {
                $slot = DB::table('quota_slot')->where('id',$data['slot'])->first();
                abort_unless(now()->lt(\Illuminate\Support\Carbon::parse($slot->day.' 12:00')->subDay()),409,'已過預約截止時間');
                abort_unless(DB::table('quota_day')->where('day',$slot->day)->whereColumn('reserved','<','capacity')->increment('reserved'),409,'日配額已滿');
                abort_unless(DB::table('quota_slot')->where('id',$slot->id)->whereColumn('reserved','<','capacity')->increment('reserved'),409,'時段已滿');
                return DB::table('booking')->insertGetId(['slot_id'=>$slot->id,'vehicle'=>$data['vehicle']]);
            }, 3);
        } catch (\Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                abort(409,'車輛已有同時段預約');
            }
            throw $error;
        }
        return response()->json(['id'=>$id],201);
    });
    Route::post('/bookings/{id}/status', function (Request $request, int $id) {
        $status = $request->validate(['status'=>'required|in:cancelled,used,no_show'])['status'];
        DB::transaction(function () use ($id,$status) {
            $booking = DB::table('booking')->where('id',$id)->lockForUpdate()->first();
            abort_unless($booking,404);
            abort_unless($booking->status === 'booked',409,'預約已結束');
            $slot = DB::table('quota_slot')->where('id',$booking->slot_id)->first();
            $start = \Illuminate\Support\Carbon::parse($slot->day.' '.$slot->opens);
            $allowed = match ($status) {
                'cancelled' => now()->lt(\Illuminate\Support\Carbon::parse($slot->day.' 12:00')->subDay()),
                'used' => now()->betweenIncluded($start,$start->copy()->addHour()),
                'no_show' => now()->gt($start->copy()->addHour()),
            };
            abort_unless($allowed,409,'目前時間不允許此操作');
            if ($status === 'cancelled') {
                DB::table('quota_day')->where('day',$slot->day)->decrement('reserved');
                DB::table('quota_slot')->where('id',$slot->id)->decrement('reserved');
            }
            DB::table('booking')->where('id',$id)->update(['status'=>$status]);
        }, 3);
        return ['status'=>$status];
    })->whereNumber('id');
    Route::get('/bookings/{id}/audit', fn(int $id) => DB::table('booking_audit')->where('booking_id',$id)->orderBy('id')->get())->whereNumber('id');
});
