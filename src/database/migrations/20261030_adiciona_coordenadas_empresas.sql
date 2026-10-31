ALTER TABLE empresas
    ADD COLUMN latitude DECIMAL(10,8) NULL
    AFTER estado,
    ADD COLUMN longitude DECIMAL(11,8) NULL
    AFTER latitude;
