CREATE TABLE be_groups (
	tx_begroups_kind int(11) DEFAULT '0' NOT NULL,
	`subgroup_fm` 	varchar(255) DEFAULT ''   NOT NULL,
	`subgroup_pm` 	varchar(255) DEFAULT ''   NOT NULL,
    `subgroup_ws` 	varchar(255) DEFAULT ''   NOT NULL,
	`subgroup_r`  	varchar(255) DEFAULT ''   NOT NULL,
    `subgroup_pa` 	varchar(255) DEFAULT ''   NOT NULL,
	`subgroup_ts` 	varchar(255) DEFAULT ''   NOT NULL,
	`subgroup_l`  	varchar(255) DEFAULT ''   NOT NULL,
	`subgroup_cat`  varchar(255) DEFAULT ''   NOT NULL
);