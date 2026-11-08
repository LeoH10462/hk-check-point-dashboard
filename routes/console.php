<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('traffic:import {file} {source}', function () {
    $source = $this->argument('source');
    if (!in_array($source, ['daily','festival'], true)) {
        throw new InvalidArgumentException('Expected daily or festival');
    }
    $file = new SplFileObject($this->argument('file'));
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
    $header = $file->fgetcsv(',', '"', '');
    if (!str_contains((string) $header[0], 'Date')) {
        throw new RuntimeException('Unexpected CSV header');
    }
    $offset = (int) ($source === 'festival');
    $aliases = DB::table('checkpoint_alias')->pluck('checkpoint_code','alias')->all();
    $count = 0;
    DB::transaction(function () use ($file, $offset, $source, &$aliases, &$count) {
        $batch = [];
        while (!$file->eof()) {
            $row = $file->fgetcsv(',', '"', '');
            if (!$row || $row[0] === null || trim($row[0]) === '') {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat($offset ? '!j/n/Y' : '!d-m-Y', trim($row[0]));
            $name = trim($row[1 + $offset]);
            $direction = trim($row[2 + $offset]);
            $numbers = array_map(fn($value) => str_replace(',', '', trim($value)), array_slice($row, 3 + $offset, 4));
            if (!$date || ($errors = DateTimeImmutable::getLastErrors()) || !in_array($direction,['Arrival','Departure'],true)
                || count($numbers) !== 4 || count(array_filter($numbers,'ctype_digit')) !== 4
                || array_sum(array_slice($numbers,0,3)) !== (int) $numbers[3]) {
                throw new RuntimeException('Invalid traffic row at line '.($file->key()+1));
            }
            $day = $date->format('Y-m-d');
            if ($source === 'festival' && $day >= '2021-01-01') {
                continue;
            }
            if (!isset($aliases[$name])) {
                $code = 'X'.substr(hash('sha256', $name),0,11);
                DB::table('checkpoint')->insertOrIgnore(['code'=>$code,'name'=>$name]);
                DB::table('checkpoint_alias')->insertOrIgnore(['alias'=>$name,'checkpoint_code'=>$code]);
                $aliases[$name] = $code;
            }
            $record = ['checkpoint_code'=>$aliases[$name],'day'=>$day,'direction'=>$direction,
                'residents'=>$numbers[0],'mainland'=>$numbers[1],'other'=>$numbers[2],'source'=>$source];
            $batch[$record['checkpoint_code'].$day.$direction] = $record;
            $count++;
            if (count($batch) >= 500) {
                DB::table('daily_traffic')->upsert(array_values($batch),['checkpoint_code','day','direction'],['residents','mainland','other','source']);
                $batch = [];
            }
        }
        if ($batch) {
            DB::table('daily_traffic')->upsert(array_values($batch),['checkpoint_code','day','direction'],['residents','mainland','other','source']);
        }
    });
    $this->info("Processed $count rows");
});

Artisan::command('traffic:reconcile {checkpoint} {day} {direction=Arrival}', function () {
    $key = ['checkpoint_code'=>$this->argument('checkpoint'),'day'=>$this->argument('day'),'direction'=>$this->argument('direction')];
    $result = DB::transaction(function () use ($key) {
        $published = DB::table('daily_traffic')->where($key)->lockForUpdate()->first();
        if (!$published) {
            throw new RuntimeException('No imported daily record matches this selection');
        }
        DB::table('demo_reconciliation')->where($key)->delete();
        $sequence = 0;
        $batch = [];
        foreach (['residents'=>'resident','mainland'=>'mainland','other'=>'other'] as $column=>$category) {
            for ($i=0; $i<$published->$column; $i++) {
                $batch[] = $key + ['seq'=>++$sequence,'category'=>$category];
                if (count($batch) === 1000) {
                    DB::table('demo_reconciliation')->insert($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('demo_reconciliation')->insert($batch);
        }
        $actual = DB::table('demo_reconciliation')->where($key)->selectRaw('category, COUNT(*) AS n')->groupBy('category')->pluck('n','category');
        return ['published'=>['resident'=>$published->residents,'mainland'=>$published->mainland,'other'=>$published->other],
            'generated'=>array_replace(['resident'=>0,'mainland'=>0,'other'=>0], $actual->all())];
    });
    $this->line(json_encode($result, JSON_PRETTY_PRINT));
    return $result['published'] == $result['generated'] ? 0 : 1;
});
