CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ping_time` TIMESTAMP NOT NULL,
  `source` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  `packets_transmitted` int(3) NOT NULL,
  `packets_received` int(3) NOT NULL,
  `packet_loss_percent` int(3) NOT NULL,
  `overall_time_ms` int(3) NOT NULL,
  `min_ms` float(6,3) NOT NULL,
  `avg_ms` float(6,3) NOT NULL,
  `max_ms` float(6,3) NOT NULL,
  `mdev_ms` float(6,3) NOT NULL,
  PRIMARY KEY (`id`)
);
