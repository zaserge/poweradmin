<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010 Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2023 Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Poweradmin\DnsRecord;

/**
 * Export Zone to BIND file format. 
 * Output to stdout due to possible large zone size.
 *
 * @param int $zone_id Domain ID
 * @param bool $pretty Do some formating and cleanup or not
 */
function export_zone_bind($zone_id, $pretty = "true")
{   
    $origin = DnsRecord::get_domain_name_by_id($zone_id);
    $max_name_len = 0;

    $records = DnsRecord::get_records_from_domain_id($zone_id, 0, 999999, 'type');

    # find longest domain name
    foreach ($records as $record) {
        $max_name_len = strlen($record['name']) > $max_name_len ? strlen($record['name']) : $max_name_len;
    }

    if ($pretty) {
        # remove lenght of oringin and stip trailing dot if presets
        $max_name_len -= strlen($origin);
        
        if ($max_name_len > 0) {
            --$max_name_len;
        }

        echo '$ORIGIN ', $origin, ".", PHP_EOL;
    } else {
                echo '$ORIGIN .', PHP_EOL;
    }

    $prev_name = '';

    foreach ($records as $record) {
        if ($pretty) {
            # remove origin and trailing dot from domain name
            $name = preg_replace('/' . $origin . '$/', '@', $record['name']);
            $name = preg_replace('/\.@$/', '', $name);

            if ($prev_name == $name) {   # olny for '@': " && $name == '@') {"
                $name = '';
            } else {
                $prev_name = $name;
            }

            switch ($record['type']) {
                case 'SOA':
                    echo str_pad($name, $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t";
                    $soa = explode(" ", $record['content']);
                    echo $soa[0], ". ", $soa[1], " ( ", PHP_EOL; 
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t\t", $soa[2], " ; serial", PHP_EOL;
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t\t", $soa[3], " ; refresh", PHP_EOL;
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t\t", $soa[4], " ; retry", PHP_EOL;
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t\t", $soa[5], " ; expire", PHP_EOL;
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t\t", $soa[6], " ; minimum", PHP_EOL;
                    echo str_repeat(" ", $max_name_len), "\t\t\t\t)", PHP_EOL;
                    break;
                case 'NS':
                case 'CNAME':
                case 'DNAME':
                case 'PTR':
                    # add trailing dot to certain items
                    echo str_pad($name, $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['content'], ".", PHP_EOL;
                    break;
                case 'MX':
                case 'SRV':
                    # add trailing dot to certain items
                    echo str_pad($name, $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['prio'], " ", $record['content'], ".", PHP_EOL;
                    break;
                
                default:
                    echo str_pad($name, $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['content'], PHP_EOL;
                    break;
            }
        } else {
            switch ($record['type']) {
                case 'SOA':
                    echo str_pad($record['name'] . ".", $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t";
                    $soa = explode(" ", $record['content']);
                    echo $soa[0], ". ", $soa[1], " ( ", $soa[2], " ", $soa[3], " ", $soa[4], " ";
                    echo $soa[5], " ", $soa[6], " )", PHP_EOL; 
                    break;
                case 'NS':
                case 'CNAME':
                case 'DNAME':
                case 'PTR':
                    # add trailing dot to certain items
                    echo str_pad($record['name'] . ".", $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['content'], ".", PHP_EOL;
                    break;
                case 'MX':
                case 'SRV':
                    # add trailing dot to certain items
                    echo str_pad($record['name'] . ".", $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['prio'], " ", $record['content'], ".", PHP_EOL;
                    break;

                    default:
                    echo str_pad($record['name'] . ".", $max_name_len), "\t", $record['ttl'], "\t";
                    echo "IN\t", $record['type'], "\t", $record['content'], PHP_EOL;
                    break;
            }
        }
    }
}

/**
 * Export Zone to BIND file format. 
 * Output to stdout due to possible large zone size.
 *
 * @param int $zone_id Domain ID
 * @param string $cvs_header Set csv header 'present' or 'absent'
 */
function export_zone_csv($zone_id, $csv_header='absent')
{    
    $records = DnsRecord::get_records_from_domain_id($zone_id, 0, 999999, 'type');
    
    if ($csv_header == 'present') {
        echo "name,ttl,type,prio,content", PHP_EOL;
    }

    foreach ($records as $record) {
        echo $record['name'], ",", $record['ttl'], ",", $record['type'], ",";
        echo $record['prio'], ",", $record['content'], PHP_EOL;
    }
}
