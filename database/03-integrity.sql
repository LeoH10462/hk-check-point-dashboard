ALTER TABLE checkpoint_alias ADD FOREIGN KEY (checkpoint_code) REFERENCES checkpoint(code);
ALTER TABLE daily_traffic ADD FOREIGN KEY (checkpoint_code) REFERENCES checkpoint(code);
ALTER TABLE crossing ADD FOREIGN KEY (traveller_id) REFERENCES traveller(id),
    ADD FOREIGN KEY (checkpoint_code) REFERENCES checkpoint(code);
ALTER TABLE quota_slot ADD FOREIGN KEY (day) REFERENCES quota_day(day);
ALTER TABLE booking ADD FOREIGN KEY (slot_id) REFERENCES quota_slot(id);
ALTER TABLE booking_audit ADD FOREIGN KEY (booking_id) REFERENCES booking(id);
ALTER TABLE simulation ADD FOREIGN KEY (checkpoint_code) REFERENCES checkpoint(code);
INSERT INTO checkpoint(code,name) VALUES ('MACAO','Macao Ferry Terminal');
INSERT INTO checkpoint_alias VALUES ('Macau Ferry Terminal','MACAO'),('Macao Ferry Terminal','MACAO');
