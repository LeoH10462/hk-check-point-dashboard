SET NAMES utf8mb4;
INSERT INTO checkpoint(code,name,chinese,transport,lanes) VALUES
('LWS','Lo Wu','羅湖','港鐵東鐵綫',16),('LSC','Lok Ma Chau Spur Line','落馬洲支線','港鐵東鐵綫',14),
('MKT','Man Kam To','文錦渡','跨境巴士',5),('SBC','Shenzhen Bay','深圳灣','專營巴士／跨境巴士',12),
('HYW','Heung Yuen Wai','香園圍','專營巴士／跨境巴士',10),('HZM','Hong Kong-Zhuhai-Macao Bridge','港珠澳大橋','穿梭巴士／專營巴士',10),
('STK','Sha Tau Kok','沙頭角','跨境巴士（以服務公告為準）',4),
('LMC','Lok Ma Chau','落馬洲','跨境穿梭巴士',8),('HGP','Huanggang','皇崗','跨境穿梭巴士',8);
INSERT INTO checkpoint_alias SELECT name,code FROM checkpoint;
INSERT INTO checkpoint_alias VALUES ('Hong Kong-Zhuhai-Macau Bridge','HZM'),('Hong Kong-Zhuhai Macao-Bridge','HZM');
INSERT INTO traveller(display_name,demo_document,fixture_note) VALUES
('示範旅客 01','DEMO-0001',NULL),('示範旅客 02','DEMO-0002','測試案例：資料需人工覆核'),
('示範旅客 03','DEMO-0003','測試案例：曾有虛構違規記錄');
INSERT INTO crossing(traveller_id,checkpoint_code,occurred_at,direction) VALUES
(1,'LWS',CURRENT_TIMESTAMP,'Arrival'),(1,'LSC',CURRENT_TIMESTAMP - INTERVAL 3 DAY,'Departure'),
(2,'SBC',CURRENT_TIMESTAMP,'Arrival'),(3,'HZM',CURRENT_TIMESTAMP - INTERVAL 1 DAY,'Arrival');
