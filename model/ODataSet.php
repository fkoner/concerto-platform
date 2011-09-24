<?php

/*
  Concerto Testing Platform,
  Web based adaptive testing platform utilizing R language for computing purposes.

  Copyright (C) 2011  Psychometrics Centre, Cambridge University

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class ODataSet extends OTable {

    public $name = "";
    public $value = "";
    public $position = 0;

    public static function get_all($sort="`position` ASC") {
        $res = array();
        $sql = sprintf("SELECT * FROM `%s` ORDER BY %s", static::get_mysql_table(), $sort);
        $z = mysql_query($sql);
        while ($r = mysql_fetch_array($z)) {
            array_push($res, static::from_mysql_result($r));
        }
        return $res;
    }

    public static function from_value($value) {
        return self::from_property(array("value" => $value), false);
    }

}

?>