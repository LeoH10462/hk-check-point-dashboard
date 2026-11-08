SET NAMES utf8mb4;
UPDATE checkpoint SET
    chinese = CASE code WHEN 'LWS' THEN '羅湖' WHEN 'LSC' THEN '落馬洲支線' WHEN 'MKT' THEN '文錦渡'
        WHEN 'SBC' THEN '深圳灣' WHEN 'HYW' THEN '香園圍' WHEN 'HZM' THEN '港珠澳大橋' WHEN 'STK' THEN '沙頭角'
        WHEN 'LMC' THEN '落馬洲' WHEN 'HGP' THEN '皇崗' ELSE chinese END,
    transport = CASE code WHEN 'LWS' THEN '港鐵東鐵綫' WHEN 'LSC' THEN '港鐵東鐵綫' WHEN 'MKT' THEN '跨境巴士'
        WHEN 'SBC' THEN '專營巴士／跨境巴士' WHEN 'HYW' THEN '專營巴士／跨境巴士'
        WHEN 'HZM' THEN '穿梭巴士／專營巴士' WHEN 'STK' THEN '跨境巴士（以服務公告為準）'
        WHEN 'LMC' THEN '跨境穿梭巴士' WHEN 'HGP' THEN '跨境穿梭巴士' ELSE transport END;
UPDATE checkpoint SET chinese = CASE name
    WHEN 'Airport' THEN '機場' WHEN 'China Ferry Terminal' THEN '中國客運碼頭'
    WHEN 'Express Rail Link West Kowloon' THEN '高鐵西九龍站' WHEN 'Harbour Control' THEN '港口管制'
    WHEN 'Hung Hom' THEN '紅磡' WHEN 'Kai Tak Cruise Terminal' THEN '啟德郵輪碼頭'
    WHEN 'Macao Ferry Terminal' THEN '港澳客輪碼頭' WHEN 'Tuen Mun Ferry Terminal' THEN '屯門客運碼頭'
    ELSE chinese END;
UPDATE traveller SET
    display_name = CASE demo_document WHEN 'DEMO-0001' THEN '示範旅客 01'
        WHEN 'DEMO-0002' THEN '示範旅客 02' WHEN 'DEMO-0003' THEN '示範旅客 03' ELSE display_name END,
    fixture_note = CASE demo_document WHEN 'DEMO-0002' THEN '測試案例：資料需人工覆核'
        WHEN 'DEMO-0003' THEN '測試案例：曾有虛構違規記錄' ELSE fixture_note END
WHERE demo_document IN ('DEMO-0001','DEMO-0002','DEMO-0003');
