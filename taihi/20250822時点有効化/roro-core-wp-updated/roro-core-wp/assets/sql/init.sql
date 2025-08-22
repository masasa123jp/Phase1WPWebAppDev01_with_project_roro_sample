--
-- SQL initialization for Roro Core plugin
--

-- This file contains example SQL statements to create the custom tables used by
-- the Roro Core plugin. These statements will be executed by the activator
-- during plugin activation via dbDelta(). You should replace these with the
-- actual schema definitions as your project evolves.

-- Example table for storing demo data
CREATE TABLE IF NOT EXISTS {prefix}roro_example (
    id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
    time DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    text VARCHAR(255) NOT NULL,
    PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;