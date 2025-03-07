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

namespace Poweradmin;

/**
 *  Template functions
 *
 * @package Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2023 Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */
class ZoneTemplate
{
    /** Get a list of all available zone templates
     *
     * @param int $userid User ID
     *
     * @return mixed[] array of zone templates [id,name,descr]
     */
    public static function get_list_zone_templ(int $userid): array
    {
        global $db;

        $query = "SELECT zt.id, zt.name, zt.descr, u.username, u.fullname, COUNT(z.zone_templ_id) as zones_linked
            FROM zone_templ zt
            LEFT JOIN users u ON zt.owner = u.id
            LEFT JOIN zones z ON zt.id = z.zone_templ_id";
        $params = [];

        if (!do_hook('verify_permission', 'user_is_ueberuser')) {
            $query .= " WHERE zt.owner = :userid OR zt.owner = 0";
            $params[':userid'] = $userid;
        }

        $query .= " GROUP BY zt.id, zt.name, zt.descr, u.username, u.fullname ORDER BY zt.name";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Add a zone template
     *
     * @param mixed[] $details zone template details
     * @param $userid User ID that owns template
     *
     * @return boolean true on success, false otherwise
     */
    public static function add_zone_templ($details, $userid)
    {
        global $db;

        $zone_name_exists = ZoneTemplate::zone_templ_name_exists($details['templ_name']);
        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to add a zone template."));
            return false;
        } elseif ($zone_name_exists != '0') {
            error(_('Zone template with this name already exists, please choose another one.'));
        } else {
            $stmt = $db->prepare("INSERT INTO zone_templ (name, descr, owner) VALUES (:name, :descr, :owner)");
            $stmt->execute([
                ':name' => $details['templ_name'],
                ':descr' => $details['templ_descr'],
                ':owner' => isset($details['templ_global']) ? 0 : $userid
            ]);
            return true;
        }
        return false;
    }

    public static function get_zone_templ_name($zone_id)
    {
        global $db;

        $stmt = $db->prepare("SELECT zt.name FROM zones z JOIN zone_templ zt ON zt.id = z.zone_templ_id WHERE z.domain_id = :zone_id");
        $stmt->execute([':zone_id' => $zone_id]);
        $result = $stmt->fetch();

        return $result ? $result['name'] : '';
    }

    /** Get name and description of template based on template ID
     *
     * @param int $zone_templ_id Zone template ID
     *
     * @return mixed[] zone template details
     */
    public static function get_zone_templ_details($zone_templ_id)
    {
        global $db;

        $query = "SELECT *"
            . " FROM zone_templ"
            . " WHERE id = " . $db->quote($zone_templ_id, 'integer');

        $result = $db->query($query);
        return $result->fetch();
    }

    /** Delete a zone template
     *
     * @param int $zone_templ_id Zone template ID
     *
     * @return boolean true on success, false otherwise
     */
    public static function delete_zone_templ($zone_templ_id)
    {
        global $db;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to delete zone templates."));
            return false;
        } else {
            // Delete the zone template
            $query = "DELETE FROM zone_templ"
                . " WHERE id = " . $db->quote($zone_templ_id, 'integer');
            $db->query($query);

            // Delete the zone template records
            $query = "DELETE FROM zone_templ_records"
                . " WHERE zone_templ_id = " . $db->quote($zone_templ_id, 'integer');
            $db->query($query);

            // Delete references to zone template
            $query = "DELETE FROM records_zone_templ"
                . " WHERE zone_templ_id = " . $db->quote($zone_templ_id, 'integer');
            $db->query($query);
            return true;
        }
    }

    /** Delete all zone templates for specific user
     *
     * @param $userid User ID
     *
     * @return boolean true on success, false otherwise
     */
    public static function delete_zone_templ_userid($userid)
    {
        global $db;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to delete zone templates."));
            return false;
        } else {
            $query = "DELETE FROM zone_templ"
                . " WHERE owner = " . $db->quote($userid, 'integer');
            $db->query($query);
            return true;
        }
    }

    /** Count zone template records
     *
     * @param int $zone_templ_id Zone template ID
     *
     * @return boolean true on success, false otherwise
     */
    public static function count_zone_templ_records($zone_templ_id)
    {
        global $db;
        $query = "SELECT COUNT(id) FROM zone_templ_records WHERE zone_templ_id = " . $db->quote($zone_templ_id, 'integer');
        return $db->queryOne($query);
    }

    /** Check if zone template exist
     *
     * @param int $zone_templ_id Zone template ID
     *
     * @return boolean true on success, false otherwise
     */
    public static function zone_templ_id_exists($zone_templ_id)
    {
        global $db;
        $query = "SELECT COUNT(id) FROM zone_templ WHERE id = " . $db->quote($zone_templ_id, 'integer');
        return $db->queryOne($query);
    }

    /** Get a zone template record from an id
     *
     * Retrieve all fields of the record and send it back to the function caller.
     *
     * @param int $id zone template record id
     *
     * @return mixed[] zone template record
     * [id,zone_templ_id,name,type,content,ttl,prio] or -1 if nothing is found
     */
    public static function get_zone_templ_record_from_id($id)
    {
        global $db;
        if (is_numeric($id)) {
            $result = $db->queryRow("SELECT id, zone_templ_id, name, type, content, ttl, prio FROM zone_templ_records WHERE id=" . $db->quote($id, 'integer'));
            if ($result) {
                return array(
                    "id" => $result["id"],
                    "zone_templ_id" => $result["zone_templ_id"],
                    "name" => $result["name"],
                    "type" => $result["type"],
                    "content" => $result["content"],
                    "ttl" => $result["ttl"],
                    "prio" => $result["prio"],
                );
            } else {
                return -1;
            }
        } else {
            error(sprintf(_('Invalid argument(s) given to function %s'), "get_zone_templ_record_from_id"));
        }
    }

    /** Get all zone template records from a zone template id
     *
     * Retrieve all fields of the records and send it back to the function caller.
     *
     * @param int $id zone template ID
     * @param int $rowstart Starting row (default=0)
     * @param int $rowamount Number of rows per query (default=999999)
     * @param string $sortby Column to sort by (default='name')
     *
     * @return mixed[] zone template records numerically indexed
     * [id,zone_templd_id,name,type,content,ttl,pro] or empty array if nothing is found
     */
    public static function get_zone_templ_records($id, $rowstart = 0, $rowamount = 999999, $sortby = 'name')
    {
        global $db;

        if (is_numeric($id)) {
            $db->setLimit($rowamount, $rowstart);
            $result = $db->query("SELECT id FROM zone_templ_records WHERE zone_templ_id=" . $db->quote($id, 'integer') . " ORDER BY " . $sortby);
            $ret[] = array();
            $retcount = 0;
            while ($r = $result->fetch()) {
                // Call get_record_from_id for each row.
                $ret[$retcount] = ZoneTemplate::get_zone_templ_record_from_id($r["id"]);
                $retcount++;
            }
            return ($retcount > 0 ? $ret : []);
        } else {
            error(sprintf(_('Invalid argument(s) given to function %s'), "get_zone_templ_records"));
        }
    }

    /** Add a record for a zone template
     *
     * This function validates and if correct it inserts it into the database.
     * TODO: actual validation?
     *
     * @param int $zone_templ_id zone template ID
     * @param string $name name part of record
     * @param string $type record type
     * @param string $content record content
     * @param int $ttl TTL
     * @param int $prio Priority
     *
     * @return boolean true if successful, false otherwise
     */
    public static function add_zone_templ_record($zone_templ_id, $name, $type, $content, $ttl, $prio)
    {
        global $db;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to add a record to this zone."));
            return false;
        }

        if ($content == '') {
            error(_('Your content field doesnt have a legit value.'));
            return false;
        }

        if ($name == '') {
            error(_('Invalid hostname.'));
            return false;
        }

        if (!Dns::is_valid_rr_prio($prio, $type)) {
            error(_('Invalid value for prio field.'));
            return false;
        }

        $query = "INSERT INTO zone_templ_records (zone_templ_id, name, type, content, ttl, prio) VALUES ("
            . $db->quote($zone_templ_id, 'integer') . ","
            . $db->quote($name, 'text') . ","
            . $db->quote($type, 'text') . ","
            . $db->quote($content, 'text') . ","
            . $db->quote($ttl, 'integer') . ","
            . $db->quote($prio, 'integer') . ")";
        $db->query($query);

        return true;
    }

    /** Modify zone template reocrd
     *
     * Edit a record for a zone template.
     * This function validates it if correct it inserts it into the database.
     *
     * @param mixed[] $record zone record array
     *
     * @return boolean true on success, false otherwise
     */
    public static function edit_zone_templ_record($record)
    {
        global $db;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to edit this record."));
            return false;
        }

        if ($record['name'] == "") {
            error(_('Invalid hostname.'));
            return false;
        }

        if (!Dns::is_valid_rr_prio($record['prio'], $record['type'])) {
            error(_('Invalid value for prio field.'));
            return false;
        }

        $query = "UPDATE zone_templ_records
                                SET name=" . $db->quote($record['name'], 'text') . ",
                                type=" . $db->quote($record['type'], 'text') . ",
                                content=" . $db->quote($record['content'], 'text') . ",
                                ttl=" . $db->quote($record['ttl'], 'integer') . ",
                                prio=" . $db->quote(isset($record['prio']) ? $record['prio'] : 0, 'integer') . "
                                WHERE id=" . $db->quote($record['rid'], 'integer');
        $db->query($query);
        return true;
    }

    /** Delete a record for a zone template by a given id
     *
     * @param int $rid template record id
     *
     * @return boolean true on success, false otherwise
     */
    public static function delete_zone_templ_record($rid)
    {
        global $db;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to delete this record."));
            return false;
        } else {
            $query = "DELETE FROM zone_templ_records WHERE id = " . $db->quote($rid, 'integer');
            $db->query($query);
            return true;
        }
    }

    /** Check if the session user is the owner for the zone template
     *
     * @param int $zone_templ_id zone template id
     * @param int $userid user id
     *
     * @return boolean true on success, false otherwise
     */
    public static function get_zone_templ_is_owner($zone_templ_id, $userid)
    {
        global $db;

        $query = "SELECT owner FROM zone_templ WHERE id = " . $db->quote($zone_templ_id, 'integer');
        $result = $db->queryOne($query);

        if ($result == $userid) {
            return true;
        } else {
            return false;
        }
    }

    /** Add a zone template from zone / another template
     *
     * @param string $template_name template name
     * @param string $description description
     * @param int $userid user id
     * @param mixed[] $records array of zone records
     * @param string $domain domain to substitute with '[ZONE]' (optional) [default=null]
     *
     * @return boolean true on success, false otherwise
     */
    public static function add_zone_templ_save_as($template_name, $description, $userid, $records, $domain = null)
    {
        global $db;
        global $db_type;

        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to add a zone template."));
            return false;
        } else {
            $result = $db->beginTransaction();

            $query = "INSERT INTO zone_templ (name, descr, owner)
			VALUES ("
                . $db->quote($template_name, 'text') . ", "
                . $db->quote($description, 'text') . ", "
                . $db->quote($userid, 'integer') . ")";

            $result = $db->exec($query);

            if ($db_type == 'pgsql') {
                $zone_templ_id = $db->lastInsertId('zone_templ_id_seq');
            } else {
                $zone_templ_id = $db->lastInsertId();
            }

            foreach ($records as $record) {
                $name = $domain ? preg_replace('/' . $domain . '/', '[ZONE]', $record['name']) : $record['name'];
                $content = $domain ? preg_replace('/' . $domain . '/', '[ZONE]', $record['content']) : $record['content'];

                $query2 = "INSERT INTO zone_templ_records (zone_templ_id, name, type, content, ttl, prio) VALUES ("
                    . $db->quote($zone_templ_id, 'integer') . ","
                    . $db->quote($name, 'text') . ","
                    . $db->quote($record['type'], 'text') . ","
                    . $db->quote($content, 'text') . ","
                    . $db->quote($record['ttl'], 'integer') . ","
                    . $db->quote(isset($record['prio']) ? $record['prio'] : 0, 'integer') . ")";
                $db->exec($query2);
            }

            $db->commit();
        }
        return true;
    }

    /** Get list of all zones using template
     *
     * @param int $zone_templ_id zone template id
     * @param int $userid user id
     *
     * @return array array of zones ids
     */
    public static function get_list_zone_use_templ(int $zone_templ_id, int $userid): array
    {
        global $db;

        $perm_edit = Permission::getEditPermission();

        $sql_add = '';
        if ($perm_edit != "all") {
            $sql_add = " AND zones.domain_id = domains.id
				AND zones.owner = " . $db->quote($userid, 'integer');
        }

        $query = "SELECT domains.id,
			domains.name,
			domains.type,
			Record_Count.count_records
			FROM domains
			LEFT JOIN zones ON domains.id=zones.domain_id
			LEFT JOIN (
				SELECT COUNT(domain_id) AS count_records, domain_id FROM records GROUP BY domain_id
			) Record_Count ON Record_Count.domain_id=domains.id
			WHERE 1=1" . $sql_add . "
                        AND zone_templ_id = " . $db->quote($zone_templ_id, 'integer') . "
			GROUP BY domains.name, domains.id, domains.type, Record_Count.count_records";

        $result = $db->query($query);

        $zone_list = array();
        while ($zone = $result->fetch()) {
            $zone_list[] = $zone['id'];
        }
        return $zone_list;
    }

    /** Modify zone template
     *
     * @param mixed[] $details array of new zone template details
     * @param int $zone_templ_id zone template id
     *
     * @return boolean true on success, false otherwise
     */
    public static function edit_zone_templ($details, $zone_templ_id, $user_id)
    {
        global $db;
        $zone_name_exists = ZoneTemplate::zone_templ_name_exists($details['templ_name'], $zone_templ_id);
        if (!(do_hook('verify_permission', 'zone_master_add'))) {
            error(_("You do not have the permission to add a zone template."));
            return false;
        } elseif ($zone_name_exists != '0') {
            error(_('Zone template with this name already exists, please choose another one.'));
            return false;
        } else {
            $query = 'UPDATE zone_templ SET name=:templ_name, descr=:templ_descr';
            $params = [
                "templ_name" => $details['templ_name'],
                "templ_descr" => $details['templ_descr'],
                "templ_id" => $zone_templ_id
            ];

            if (isset($details['templ_global'])) {
                $query .= ', owner=0';
            } else {
                $query .= ', owner=:templ_owner';
                $params['templ_owner'] = $user_id;
            }
            $query .= ' WHERE id=:templ_id';
            $stmt = $db->prepare($query);

            $stmt->execute($params);

            return true;
        }
    }

    /** Check if zone template name exists
     *
     * @param string $zone_templ_name zone template name
     * @param int $zone_templ_id zone template id (optional) [default=null]
     *
     * @return array|bool number of matching templates
     */
    public static function zone_templ_name_exists($zone_templ_name, $zone_templ_id = null)
    {
        global $db;

        $sql_add = '';
        if ($zone_templ_id) {
            $sql_add = " AND id != " . $db->quote($zone_templ_id, 'integer');
        }

        $query = "SELECT COUNT(id) FROM zone_templ WHERE name = " . $db->quote($zone_templ_name, 'text') . $sql_add;
        return $db->queryOne($query);
    }

    /** Parse string and substitute domain and serial
     *
     * @param string $val string to parse containing tokens '[ZONE]' and '[SERIAL]'
     * @param string $domain domain to substitute for '[ZONE]'
     *
     * @return string interpolated/parsed string
     */
    public static function parse_template_value($val, $domain): string
    {
        global $dns_ns1, $dns_ns2, $dns_ns3, $dns_ns4, $dns_hostmaster;

        $serial = date("Ymd");
        $serial .= "00";

        $val = str_replace('[ZONE]', $domain, $val);
        $val = str_replace('[SERIAL]', $serial, $val);
        $val = str_replace('[NS1]', $dns_ns1, $val);
        $val = str_replace('[NS2]', $dns_ns2, $val);
        $val = str_replace('[NS3]', $dns_ns3, $val);
        $val = str_replace('[NS4]', $dns_ns4, $val);
        return str_replace('[HOSTMASTER]', $dns_hostmaster, $val);
    }
}