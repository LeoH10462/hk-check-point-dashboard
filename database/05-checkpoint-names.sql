SET NAMES utf8mb4;

UPDATE checkpoint SET name = 'New Huanggang Checkpoint', chinese = '新皇崗口岸' WHERE code = 'HGP';
UPDATE checkpoint SET name = 'Liantang / Heung Yuen Wai', chinese = '蓮塘／香園圍' WHERE code = 'HYW';
UPDATE checkpoint SET name = 'Futian / Lok Ma Chau', chinese = '福田／落馬洲' WHERE code = 'LMC';

INSERT IGNORE INTO checkpoint_alias (alias, checkpoint_code)
SELECT name, code FROM checkpoint WHERE code IN ('HGP', 'HYW', 'LMC');
