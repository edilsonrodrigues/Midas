-- Midas Server. Copyright Kitware SAS. Licensed under the Apache License 2.0.

-- MySQL database for the tracker module, version 2.0.2

CREATE TABLE IF NOT EXISTS `tracker_producer` (
    `producer_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `community_id` bigint(20) NOT NULL,
    `repository` varchar(255) NOT NULL,
    `executable_name` varchar(255) NOT NULL,
    `display_name` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `revision_url` text NOT NULL,
    PRIMARY KEY (`producer_id`),
    KEY (`community_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_scalar` (
    `scalar_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `trend_id` bigint(20) NOT NULL,
    `value` double,
    `submission_id` bigint(20) NOT NULL,
    PRIMARY KEY (`scalar_id`),
    KEY (`trend_id`),
    KEY (`submission_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_submission` (
    `submission_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `producer_id` bigint(20) NOT NULL,
    `name` varchar(255) NOT NULL DEFAULT '',
    `uuid` varchar(255) NOT NULL DEFAULT '',
    `submit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `producer_revision` varchar(255),
    `user_id` bigint(20) NOT NULL DEFAULT '-1',
    `official` tinyint(4) NOT NULL DEFAULT '1',
    `build_results_url` text NOT NULL,
    `branch` varchar(255) NOT NULL DEFAULT '',
    `extra_urls` text,
    `reproduction_command` text,
    PRIMARY KEY (`submission_id`),
    UNIQUE KEY (`uuid`),
    KEY (`user_id`),
    KEY (`submit_time`),
    KEY (`branch`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_submission2item` (
    `submission_id` bigint(20) NOT NULL,
    `item_id` bigint(20) NOT NULL,
    `label` varchar(255) NOT NULL,
    `trendgroup_id` bigint(20) NOT NULL,
    KEY (`submission_id`),
    KEY (`item_id`),
    KEY (`trendgroup_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_threshold_notification` (
    `threshold_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `trend_id` bigint(20) NOT NULL,
    `value` double,
    `comparison` varchar(2),
    `action` varchar(80) NOT NULL,
    `recipient_id` bigint(20) NOT NULL,
    PRIMARY KEY (`threshold_id`),
    KEY (`trend_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_trend` (
    `trend_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `trendgroup_id` bigint(20),
    `metric_name` varchar(255) NOT NULL,
    `display_name` varchar(255) NOT NULL,
    `unit` varchar(255) NOT NULL,
    `key_metric` tinyint(4) NOT NULL DEFAULT '0',
    PRIMARY KEY (`trend_id`),
    KEY (`trendgroup_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_trend_threshold` (
    `trend_threshold_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `producer_id` bigint(20) NOT NULL,
    `metric_name` varchar(255) NOT NULL,
    `abbreviation` varchar(255) NOT NULL DEFAULT '',
    `warning` double,
    `fail` double,
    `max` double,
    PRIMARY KEY (`trend_threshold_id`),
    KEY (`producer_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_trendgroup` (
    `trendgroup_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `producer_id` bigint(20) NOT NULL,
    `config_item_id` bigint(20),
    `test_dataset_id` bigint(20),
    `truth_dataset_id` bigint(20),
    PRIMARY KEY (`trendgroup_id`),
    KEY (`producer_id`),
    KEY (`config_item_id`),
    KEY (`test_dataset_id`),
    KEY (`truth_dataset_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_param` (
    `param_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `submission_id` bigint(20) NOT NULL,
    `param_name` varchar(255) NOT NULL,
    `param_type` enum('text', 'numeric') NOT NULL,
    `text_value` text,
    `numeric_value` double,
    PRIMARY KEY (`param_id`),
    KEY (`submission_id`),
    KEY (`param_name`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_aggregate_metric` (
    `aggregate_metric_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `aggregate_metric_spec_id` bigint(20) NOT NULL,
    `submission_id` bigint(20) NOT NULL,
    `value` double,
    PRIMARY KEY (`aggregate_metric_id`),
    KEY (`aggregate_metric_spec_id`),
    KEY (`submission_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_aggregate_metric_spec` (
    `aggregate_metric_spec_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `producer_id` bigint(20) NOT NULL,
    `name` varchar(255) NOT NULL DEFAULT '',
    `description` varchar(255) NOT NULL DEFAULT '',
    `spec` text NOT NULL DEFAULT '',
    `abbreviation` varchar(255) NOT NULL DEFAULT '',
    `warning` double,
    `fail` double,
    `max` double,
    PRIMARY KEY (`aggregate_metric_spec_id`),
    KEY (`producer_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_aggregate_metric_notification` (
    `aggregate_metric_notification_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `aggregate_metric_spec_id` bigint(20) NOT NULL,
    `branch` varchar(255) NOT NULL DEFAULT '',
    `value` double,
    `comparison` varchar(2) NOT NULL DEFAULT '',
    PRIMARY KEY (`aggregate_metric_notification_id`),
    KEY (`aggregate_metric_spec_id`),
    KEY (`branch`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tracker_user2aggregate_metric_notification` (
    `user_id` bigint(20) NOT NULL,
    `aggregate_metric_notification_id` bigint(20) NOT NULL,
    PRIMARY KEY (`user_id`, `aggregate_metric_notification_id`)
) DEFAULT CHARSET=utf8;
