DROP TABLE IF EXISTS `0_bi_statements`;
DROP TABLE IF EXISTS `0_bi_transactions`;
DROP TABLE IF EXISTS `0_bi_partners_data`;

CREATE TABLE `0_bi_statements` (
    `id`			INTEGER NOT NULL AUTO_INCREMENT,
    `bank`			VARCHAR(6),
    `account`			VARCHAR(24),
    `currency`			VARCHAR(3),
    `startBalance`		DOUBLE,
    `endBalance`		DOUBLE,
    `smtDate`			DATE,
    `number`			INTEGER,
    `seq`			INTEGER,
    `statementId`		VARCHAR(32),
    PRIMARY KEY(`id`),
    CONSTRAINT `unique_smt` UNIQUE(`bank`, `statementId`)
);

CREATE TABLE `0_bi_transactions` (
    `id`			INTEGER NOT NULL AUTO_INCREMENT,
    `smt_id`			INTEGER NOT NULL,
    
    `valueTimestamp`		DATE,
    `entryTimestamp`		DATE,
    `account`			VARCHAR(24),
    `accountName`		VARCHAR(60),
    `transactionType`		VARCHAR(3),
    `transactionCode`		VARCHAR(32),
    `transactionCodeDesc`	VARCHAR(32),
    `transactionDC`		VARCHAR(2),
    `transactionAmount`		DOUBLE,
    `transactionTitle`		VARCHAR(256),

-- information
    `status`			INTEGER default 0,
    `matchinfo`			VARCHAR(256),

-- settled info
    `fa_trans_type`		INTEGER default 0,
    `fa_trans_no`		INTEGER default 0,
    PRIMARY KEY(`id`)
);


CREATE TABLE `0_bi_partners_data` (
    `partner_id`		INTEGER,
    `partner_detail_id`		INTEGER,
    `partner_type`		INTEGER,
    `data`			varchar(256),
    CONSTRAINT `idx` UNIQUE(`partner_id`, `partner_detail_id`, `partner_type`)
);