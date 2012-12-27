SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `banned`;
CREATE TABLE IF NOT EXISTS `banned` (
  `id` int(11) unsigned NOT NULL,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL,
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` varchar(80) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL DEFAULT '1',
  `totalcount` int(11) unsigned NOT NULL DEFAULT '1',
  `uptime` tinyint(3) unsigned NOT NULL DEFAULT '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  `vote` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `log_voted`;
CREATE TABLE IF NOT EXISTS `log_voted` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `server_id` int(11) unsigned NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `ip` varchar(15) NOT NULL,
  `op` char(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `servers`;
CREATE TABLE IF NOT EXISTS `servers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL,
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` varchar(80) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL DEFAULT '1',
  `totalcount` int(11) unsigned NOT NULL DEFAULT '1',
  `uptime` tinyint(3) unsigned NOT NULL DEFAULT '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  `vote` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `toadd`;
CREATE TABLE IF NOT EXISTS `toadd` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(8) unsigned NOT NULL,
  `uname` varchar(80) NOT NULL,
  `online` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `name` tinytext NOT NULL,
  `pic_url` tinytext NOT NULL,
  `ip` varchar(30) NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `version` varchar(80) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `info` text NOT NULL,
  `oncount` int(11) unsigned NOT NULL DEFAULT '1',
  `totalcount` int(11) unsigned NOT NULL DEFAULT '1',
  `uptime` tinyint(3) unsigned NOT NULL DEFAULT '100',
  `ipaddress` varchar(15) NOT NULL,
  `sponsored` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rs_name` tinytext NOT NULL,
  `rs_pass` tinytext NOT NULL,
  `key` varchar(15) NOT NULL,
  `verified` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `online` (`online`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
