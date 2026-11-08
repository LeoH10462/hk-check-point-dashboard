CREATE TABLE checkpoint (
  code VARCHAR(12) PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE,
  chinese VARCHAR(60), transport VARCHAR(180), lanes INT NOT NULL DEFAULT 6 CHECK (lanes > 0)
);
CREATE TABLE checkpoint_alias (
  alias VARCHAR(120) PRIMARY KEY, checkpoint_code VARCHAR(12) NOT NULL
);
CREATE TABLE daily_traffic (
  checkpoint_code VARCHAR(12) NOT NULL,
  day DATE NOT NULL, direction ENUM('Arrival','Departure') NOT NULL,
  residents INT UNSIGNED NOT NULL, mainland INT UNSIGNED NOT NULL, other INT UNSIGNED NOT NULL,
  total INT UNSIGNED GENERATED ALWAYS AS (residents + mainland + other) STORED,
  source ENUM('daily','festival') NOT NULL,
  PRIMARY KEY (checkpoint_code, day, direction), INDEX traffic_day(day, direction)
);
CREATE TABLE traveller (
  id INT AUTO_INCREMENT PRIMARY KEY, display_name VARCHAR(80) NOT NULL,
  demo_document VARCHAR(32) NOT NULL UNIQUE, fixture_note VARCHAR(100)
);
CREATE TABLE crossing (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, traveller_id INT NOT NULL,
  checkpoint_code VARCHAR(12) NOT NULL,
  occurred_at DATETIME NOT NULL, direction ENUM('Arrival','Departure') NOT NULL,
  INDEX traveller_recent(traveller_id,occurred_at),
  INDEX checkpoint_recent(checkpoint_code,occurred_at)
);
CREATE TABLE booking_policy (
  effective_on DATE PRIMARY KEY, tue_wed_free BOOLEAN NOT NULL
);
INSERT INTO booking_policy VALUES ('2023-07-01',FALSE),('2025-09-01',TRUE);
CREATE TABLE quota_day (
  day DATE PRIMARY KEY, capacity INT UNSIGNED NOT NULL,
  reserved INT UNSIGNED NOT NULL DEFAULT 0, CHECK (reserved <= capacity)
);
CREATE TABLE quota_slot (
  id INT AUTO_INCREMENT PRIMARY KEY, day DATE NOT NULL,
  opens TIME NOT NULL, capacity INT UNSIGNED NOT NULL, reserved INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE(day,opens), CHECK(reserved <= capacity)
);
CREATE TABLE booking (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, slot_id INT NOT NULL,
  vehicle VARCHAR(24) NOT NULL, status ENUM('booked','cancelled','used','no_show') NOT NULL DEFAULT 'booked',
  active_vehicle VARCHAR(24) GENERATED ALWAYS AS (IF(status = 'cancelled',NULL,vehicle)) STORED,
  UNIQUE(slot_id,active_vehicle), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE booking_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT NOT NULL,
  status VARCHAR(20) NOT NULL, changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE simulation (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, checkpoint_code VARCHAR(12) NOT NULL,
  parameters JSON NOT NULL, results JSON NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE demo_reconciliation (
  checkpoint_code VARCHAR(12) NOT NULL, day DATE NOT NULL, direction ENUM('Arrival','Departure') NOT NULL,
  seq INT UNSIGNED NOT NULL, category ENUM('resident','mainland','other') NOT NULL,
  PRIMARY KEY(checkpoint_code,day,direction,seq),
  FOREIGN KEY(checkpoint_code,day,direction) REFERENCES daily_traffic(checkpoint_code,day,direction)
);
DELIMITER //
CREATE TRIGGER booking_create AFTER INSERT ON booking FOR EACH ROW
BEGIN
  INSERT INTO booking_audit(booking_id,status) VALUES(NEW.id,NEW.status);
END//
CREATE TRIGGER booking_transition BEFORE UPDATE ON booking FOR EACH ROW
BEGIN
  IF OLD.status <> 'booked' OR NEW.status NOT IN ('cancelled','used','no_show')
      OR NEW.slot_id <> OLD.slot_id OR NEW.vehicle <> OLD.vehicle THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid booking transition';
  END IF;
END//
CREATE TRIGGER booking_change AFTER UPDATE ON booking FOR EACH ROW
BEGIN
  INSERT INTO booking_audit(booking_id,status) VALUES(NEW.id,NEW.status);
END//
DELIMITER ;
