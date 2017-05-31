<?php

/**
 * This file contains all functions relating to the field types (select, radios etc). Added in 2.1.0
 * with the addition of Custom Fields.
 *
 * @copyright Benjamin Keen 2017
 * @author Benjamin Keen <ben.keen@gmail.com>
 * @package 3-0-x
 */


// -------------------------------------------------------------------------------------------------

namespace FormTools;


class FieldTypes {

    /**
     * Web browsers have built-in support various field types - inputs, dropdowns, radios etc. The semantics of
     * the field markup is "hardcoded" - i.e. they require you to enter certain characters to create something that
     * has meaning to the browser - to signify that you want an input field. Form Tools field types are a totally
     * separate layer above this: you can create field types for any old thing - google maps fields, date fields,
     * plain text fields etc. These may or may not map to "actual" form field types understood natively by the
     * browser. But in order for the Add Form process to intelligently map the raw form field types to a Form
     * Tools field type, we need to provide an (optional) mapping.
     *
     * For instance, if you create a Date field type within Form Tools, it's really just a <input type="text" />
     * field in your original form that's enhanced with the jQuery calendar within FT. However, in order to
     * provide the user with the option of *choosing* the Date field type for an input field during the Add
     * External Form process, this mapping is necessary.
     */
    public static $rawFieldTypes = array(
        "textbox"       => "word_textbox",
        "textarea"      => "word_textarea",
        "password"      => "word_password",
        "radio-buttons" => "phrase_radio_buttons",
        "checkboxes"    => "word_checkboxes",
        "select"        => "word_dropdown",
        "multi-select"  => "phrase_multi_select_dropdown",
        "file"          => "word_file"
    );

// pity this has to be hardcoded... but right now the field setting options don't have their own unique
// identifiers
    public static $defaultDatetimeFormat = "datetime:yy-mm-dd|h:mm TT|ampm`true";


    /**
     * Returns a hash of field_type_id => field type name.
     * @return array
     */
    public static function getFieldTypeNames()
    {
        Core::$db->query("
            SELECT field_type_id, field_type_name
            FROM   {PREFIX}field_types
        ");
        Core::$db->execute();

        $info = array();
        foreach (Core::$db->fetchAll() as $row) {
            $info[$row["field_type_id"]] = General::evalSmartyString($row["field_type_name"]);
        }

        return $info;
    }


    /**
     * Returns an array used for populating Field Type dropdowns. This returns an array with the following
     * structure:
     *
     * [
     *   [
     *     "group":       [ ... ]
     *     "field_types": [ ... ]
     *   ],
     *   ...
     * ]
     */
    public static function getGroupedFieldTypes()
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}list_groups
            WHERE  group_type = 'field_types'
            ORDER BY list_order
        ");
        $db->execute();

        $info = array();
        foreach ($db->fetchAll() as $row) {
            $group_id = $row["group_id"];
            $db->query("
                SELECT *
                FROM   {PREFIX}field_types
                WHERE  group_id = :group_id
                ORDER BY list_order
            ");
            $db->bind("group_id", $group_id);
            $db->execute();

            $field_types = array();
            $list = $db->fetchAll();
            foreach ($list as $field_type_info) {
                $field_type_id = $field_type_info["field_type_id"];
                $db->query("
                    SELECT *
                    FROM   {PREFIX}field_type_settings
                    WHERE  field_type_id = :field_type_id
                ");
                $db->bind("field_type_id", $field_type_id);
                $db->execute();
                $field_type_info["settings"] = $db->fetchAll();

                $db->query("
                    SELECT *
                    FROM   {PREFIX}field_type_validation_rules
                    WHERE  field_type_id = :field_type_id
                    ORDER BY list_order
                ");
                $db->bind("field_type_id", $field_type_id);
                $db->execute();

                $field_type_info["validation"] = $db->fetchAll();
                $field_types[] = $field_type_info;
            }
            $curr_group = array(
                "group"       => $row,
                "field_types" => $field_types
            );
            $info[] = $curr_group;
        }

        return $info;
    }


    /**
     * Returns all field type groups in the database (including ones with no field types in them). It
     * also returns a num_field_types key containing the number of field types in each group.
     * @return array [group ID] => group name
     *
     * Doesn't appear to be used.
     */
//    public static function getFieldTypeGroups()
//    {
//        $db = Core::$db;
//
//        $db->query("
//            SELECT *
//            FROM   {PREFIX}list_groups
//            WHERE  group_type = 'field_types'
//            ORDER BY list_order
//        ");
//
//        // inefficient
//        $info = array();
//        while ($row = mysql_fetch_assoc($query)) {
//            $group_id = $row["group_id"];
//            $count_query = mysql_query("SELECT count(*) as c FROM {$g_table_prefix}field_types WHERE group_id = $group_id");
//            $result = mysql_fetch_assoc($count_query);
//            $row["num_field_types"] = $result["c"];
//            $info[] = $row;
//        }
//
//        return $info;
//    }


    /**
     * Returns info about a field type.
     * @param integer $field_type_id
     * @param boolean $return_all_info
     */
    public static function getFieldType($field_type_id, $return_all_info = false)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}field_types
            WHERE  field_type_id = :field_type_id
        ");
        $db->bind("field_type_id", $field_type_id);
        $db->execute();
        $info = $db->fetch();

        if ($return_all_info) {
            $info["settings"]   = self::getFieldTypeSettings($field_type_id, true);
            $info["validation"] = self::getFieldTypeValidationRules($field_type_id);
        }

        return $info;
    }


    public static function getFieldTypeIdByIdentifier($identifier)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}field_types
            WHERE  field_type_identifier = :identifier
        ");
        $db->bind("identifier", $identifier);
        $db->execute();
        $info = $db->fetch();

        if (empty($info)) {
            return "";
        } else {
            return $info["field_type_id"];
        }
    }


    /**
     * This finds out the field type ID for a particular field.
     * @param integer $field_id
     * @return integer the field type ID
     */
    public static function getFieldTypeId($field_id)
    {
        Core::$db->query("
            SELECT field_type_id
            FROM {PREFIX}form_fields
            WHERE field_id = :field_id
        ");
        Core::$db->bind("field_id", $field_id);
        Core::$db->execute();

        $result = Core::$db->fetch();

        return $result["field_type_id"];
    }


    /**
     * Returns all info about a field type setting.
     * @param integer $setting_id
     *
     * Not used.
     */
//    function ft_get_field_type_setting($setting_id)
//    {
//        $db = Core::$db;
//
//        $query = mysql_query("
//		SELECT *
//		FROM   {$g_table_prefix}field_type_settings
//		WHERE  setting_id = $setting_id
//	");
//
//        $options_query = mysql_query("
//		SELECT *
//		FROM   {$g_table_prefix}field_type_setting_options
//		WHERE  setting_id = $setting_id
//		ORDER BY option_order
//	");
//
//        $options = array();
//        while ($row = mysql_fetch_assoc($options_query))
//        {
//            $options[] = array(
//            "option_text"       => $row["option_text"],
//            "option_value"      => $row["option_value"],
//            "option_order"      => $row["option_order"],
//            "is_new_sort_group" => $row["is_new_sort_group"]
//            );
//        }
//
//        $info = mysql_fetch_assoc($query);
//        $info["options"] = $options;
//
//        return $info;
//    }


    /**
     * Returns all information about a field type settings for a field type, as identified by its
     * field type identifier string.
     *
     * @param integer $field_type_id
     * @param string $field_type_setting_identifier
     * @return array
     */
    public static function getFieldTypeSettingByIdentifier($field_type_id, $field_type_setting_identifier)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}field_type_settings
            WHERE  field_type_id = :field_type_id AND
                   field_setting_identifier = :field_type_setting_identifier
        ");
        $db->bindAll(array(
            "field_type_id" => $field_type_id,
            "field_type_setting_identifier" => $field_type_setting_identifier
        ));
        $db->execute();

        $field_setting_info = $db->fetch();

        if (!empty($field_setting_info)) {
            $db->query("
                SELECT *
                FROM   {PREFIX}field_type_setting_options
                WHERE  setting_id = :setting_id
                ORDER BY option_order
            ");
            $db->bind("setting_id", $field_setting_info["setting_id"]);
            $db->execute();

            $options = array();
            foreach ($db->fetchAll() as $row) {
                $options[] = array(
                    "option_text"       => $row["option_text"],
                    "option_value"      => $row["option_value"],
                    "option_order"      => $row["option_order"],
                    "is_new_sort_group" => $row["is_new_sort_group"]
                );
            }
            $field_setting_info["options"] = $options;
        }

        return $field_setting_info;
    }



    /**
     * Returns the setting ID by its identifier.
     *
     * @param integer $field_type_id
     * @param string $field_type_setting_identifier
     * @return integer
     */
    public static function getFieldTypeSettingIdByIdentifier($field_type_id, $field_type_setting_identifier)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}field_type_settings
            WHERE  field_type_id = :field_type_id AND
                   field_setting_identifier = :field_type_setting_identifier
        ");
        $db->bindAll(array(
            "field_type_id" => $field_type_id,
            "field_type_setting_identifier" => $field_type_setting_identifier
        ));
        $db->execute();

        $field_setting_info = $db->fetch();

        return (!empty($field_setting_info)) ? $field_setting_info["setting_id"] : "";
    }


    /**
     * Returns all settings for a field type, including the options - if requested.
     *
     * The previous function should be deprecated in favour of this.
     *
     * @param mixed $field_type_id_or_ids the integer or array
     * @param boolean $return_options
     */
    public static function getFieldTypeSettings($field_type_id_or_ids, $return_options = false)
    {
        $db = Core::$db;
        if (empty($field_type_id_or_ids)) {
            return array();
        }

        if (is_array($field_type_id_or_ids)) {
            $field_type_id_str = implode(",", $field_type_id_or_ids);
            $return_one_field_type = false;
        } else {
            $field_type_id_str = $field_type_id_or_ids;
            $return_one_field_type = true;
        }

        $db->query("
            SELECT *
            FROM   {PREFIX}field_type_settings
            WHERE  field_type_id IN ($field_type_id_str)
            ORDER BY list_order
        ");
        $db->execute();

        $info = array();
        foreach ($db->fetchAll() as $row) {
            $field_type_id = $row["field_type_id"];
            $setting_id    = $row["setting_id"];

            if ($return_options) {
                $db->query("
                    SELECT *
                    FROM   {PREFIX}field_type_setting_options
                    WHERE  setting_id = :setting_id
                    ORDER BY option_order
                ");
                $db->bind("setting_id", $setting_id);
                $db->execute();

                $options = array();
                foreach ($db->fetchAll() as $option_row) {
                    $options[] = array(
                        "option_text"       => $option_row["option_text"],
                        "option_value"      => $option_row["option_value"],
                        "option_order"      => $option_row["option_order"],
                        "is_new_sort_group" => $option_row["is_new_sort_group"]
                    );
                }
                $row["options"] = $options;
            }

            // for backward compatibility
            if ($return_one_field_type) {
                $info[] = $row;
            } else {
                if (!array_key_exists($field_type_id, $info)) {
                    $info[$field_type_id] = array();
                }
                $info[$field_type_id][] = $row;
            }
        }

        return $info;
    }


    /**
     * Used on the Edit Fields page to generate the list of settings & setting-options for all field types. This
     * is used to actual create the appropriate markup in the Edit Fields dialog window. Generates the following
     * data structure:
     *
     * page_ns.field_settings["field_type_X"] = [
     *   {
     *     setting_id:  X,
     *     field_label: "",
     *     field_type:  "textbox",
     *     field_orientation: "",
     *     options: [
     *       {
     *         value: "",
     *         text:  ""
     *       },
     *       ...
     *     ]
     *   },
     *   ...
     * ]
     */
    public static function generateFieldTypeSettingsJs($params = array())
    {
        $db = Core::$db;

        $options = array_merge(array(
            "page_ns" => "page_ns",
            "js_key" => "field_type_id"
        ), $params);
        $namespace = $options["namespace"];
        $js_key = $options["js_key"];

        $minimize = true;
        $delimiter = "\n";
        if ($minimize) {
            $delimiter = "";
        }

        $db->query("
            SELECT DISTINCT field_type_id
            FROM {PREFIX}field_type_settings
        ");
        $db->execute();

        $field_type_id_to_identifier_map = self::getFieldTypeIdToIdentifierMap();
        $curr_js = array("{$options["namespace"]}.field_settings = {};");

        $field_setting_rows = array();
        foreach ($db->fetchAll() as $row) {
            $field_type_id = $row["field_type_id"];

            $db->query("
                SELECT setting_id, field_label, field_setting_identifier, field_type, field_orientation, default_value
                FROM   {PREFIX}field_type_settings
                WHERE field_type_id = :field_type_id
                ORDER BY list_order
            ");
            $db->bind("field_type_id", $field_type_id);
            $db->execute();

            $settings_js = array();
            foreach ($db->fetchAll() as $settings_row) {
                $setting_id = $settings_row["setting_id"];
                $field_label = General::evalSmartyString($settings_row["field_label"]);
                $field_setting_identifier = $settings_row["field_setting_identifier"];
                $field_type = $settings_row["field_type"];
                $default_value = $settings_row["default_value"];
                $field_orientation = $settings_row["field_orientation"];

                // now one more nested query (!!) to get all the options for this field type setting
                $db->query("
                    SELECT option_text, option_value
                    FROM   {PREFIX}field_type_setting_options
                    WHERE  setting_id = :setting_id
                    ORDER BY option_order
                ");
                $db->bind("setting_id", $setting_id);
                $db->execute();

                $options = array();
                foreach ($db->fetchAll() as $options_row) {
                    $value = $options_row["option_value"];
                    $text  = General::evalSmartyString($options_row["option_text"]);
                    $options[] = "{ value: \"$value\", text: \"$text\" }";
                }

                $options_js = implode(",$delimiter", $options);
                if (!empty($options_js)) {
                    $options_js = "\n$options_js\n    ";
                }

                $settings_js[] =<<< END
	{ setting_id: $setting_id, field_label: "$field_label", field_setting_identifier: "$field_setting_identifier", field_type: "$field_type", default_value: "$default_value", field_orientation: "$field_orientation", options: [$options_js] }
END;
            }

            if ($js_key == "field_type_id") {
                $curr_js[] = "{$namespace}.field_settings[\"field_type_$field_type_id\"] = [";
            } else {
                $field_type_identifier = $field_type_id_to_identifier_map[$field_type_id];
                $curr_js[] = "{$namespace}.field_settings[\"$field_type_identifier\"] = [";
            }

            $curr_js[] = implode(",$delimiter", $settings_js);
            $curr_js[] = "];";
        }

        $field_setting_rows[] = implode("$delimiter", $curr_js);

        return implode("$delimiter", $field_setting_rows);
    }


    /**
     * This function returns a hash containing usage information about a field type. The hash is
     * structured like so:
     *
     *   "total_num_fields" => X (the number
     *   "usage_by_form" => array (
     *     array("form_name" => "...", form_id => X, "num_fields" => Y),
     *     array("form_name" => "...", form_id => X, "num_fields" => Z)
     *   )
     *
     * @param integer $field_type
     * @return array
     *
     * Not used.
     */
//    function ft_get_field_type_usage($field_type_id)
//    {
//        global $g_table_prefix;
//
//        // grr! This should be a single query as the next
//        $query = mysql_query("SELECT DISTINCT form_id FROM {$g_table_prefix}form_fields WHERE field_type_id = $field_type_id");
//
//        $info = array();
//        while ($row = mysql_fetch_assoc($query))
//        {
//            $form_id = $row["form_id"];
//
//            $field_type_query = mysql_query("
//			SELECT count(*) as c
//			FROM {$g_table_prefix}form_fields
//			WHERE form_id = $form_id AND
//						field_type_id = $field_type_id
//		");
//            $result = mysql_fetch_assoc($field_type_query);
//
//            $info[] = array(
//            "form_id"    => $form_id,
//            "form_name"  => Forms::getFormName($form_id),
//            "num_fields" => $result["c"]
//            );
//        }
//
//        return $info;
//    }



    /**
     * Used in the Add External Form process. This generates a JS object that maps "raw" field types to those
     * field types specified in the Custom Field module. This allows the script to provide a list of appropriate
     * field types for each form field, from which the user can choose.
     *
     * Any fields that aren't mapped to a "raw" field won't get listed here. They can be used when editing forms,
     * but not when initially adding them. Also, for Option List fields (checkboxes, radios, dropdowns, multi-selects),
     * this function ONLY returns those custom fields that specify an Option List. Without it, the user wouldn't be
     * able to map the options in their form to an Option List associated with a field type.
     *
     * @return string a JS object
     */
    public static function getRawFieldTypesJs($namespace = "page_ns")
    {
        $field_types = FieldTypes::get();

        $mapped = array();
        while (list($raw_field_type, $field_type_label) = each(self::$rawFieldTypes)) {
            $curr_mapped_field_types = array();
            foreach ($field_types as $field_type_info) {
                if ($field_type_info["raw_field_type_map"] != $raw_field_type) {
                    continue;
                }

                if (in_array($raw_field_type, array("radio-buttons", "checkboxes", "select", "multi-select"))) {
                    if (empty($field_type_info["raw_field_type_map_multi_select_id"])) {
                        continue;
                    }
                }

                $curr_mapped_field_types[] = array(
                    "field_type_id"   => $field_type_info["field_type_id"],
                    "field_type_name" => General::evalSmartyString($field_type_info["field_type_name"]),
                    "compatible_field_sizes" => $field_type_info["compatible_field_sizes"],
                    "raw_field_type_map_multi_select_id" => $field_type_info["raw_field_type_map_multi_select_id"]
                );
            }

            $mapped[$raw_field_type] = $curr_mapped_field_types;
        }
        reset(self::$rawFieldTypes);

        $js = $namespace . ".raw_field_types = " . json_encode($mapped);
        return $js;
    }


    /**
     * Helper function to return a hash of field type ID => field type identifier.
     *
     * @return array
     */
    public static function getFieldTypeIdToIdentifierMap()
    {
        $field_types = FieldTypes::get();
        $map = array();
        foreach ($field_types as $field_type_info) {
            $map[$field_type_info["field_type_id"]] = $field_type_info["field_type_identifier"];
        }
        return $map;
    }


    /**
     * Returns all CSS or javascript defined for all fields types. Note: this does NOT include external files uploaded
     * through the Resources section of the Custom Fields module. That's
     *
     * @param $resource_type
     */
    public static function getFieldTypeResources($resource_type)
    {
        $db = Core::$db;

        if ($resource_type == "css") {
            $setting_name = "edit_submission_shared_resources_css";
            $db_column    = "resources_css";
        } else {
            $setting_name = "edit_submission_shared_resources_js";
            $db_column    = "resources_js";
        }

        $str = Settings::get($setting_name);
        $db->query("
            SELECT $db_column
            FROM   {PREFIX}field_types
        ");
        $db->execute();

        foreach ($db->fetchAll() as $row) {
            $str .= $row[$db_column] . "\n";
        }

        return $str;
    }


    /**
     * A simple function to return a hash of field_type_id => hash of information that's needed
     * for processing the field type and storing it in the database. Namely: the PHP processing
     * code for the field type and whether it's a date or file field.
     *
     * @return array
     */
    public static function getFieldTypeProcessingInfo()
    {
        $db = Core::$db;

        $db->query("
            SELECT field_type_id, php_processing, is_date_field, is_file_field
            FROM   {PREFIX}field_types
        ");
        $db->execute();

        $result = array();
        foreach ($db->fetchAll() as $row) {
            $result[$row["field_type_id"]] = array(
                "php_processing" => trim($row["php_processing"]),
                "is_date_field"  => $row["is_date_field"],
                "is_file_field"  => $row["is_file_field"]
            );
        }

        return $result;
    }


    /**
     * Used in the ft_update_submission function. This retrieves all setting information for a
     * field - including the field type settings that weren't overridden.
     *
     * @param $field_ids
     * @return array a hash of [field_id][identifier] = values
     */
    public static function getFormFieldFieldTypeSettings($field_ids = array(), $form_fields)
    {
        $db = Core::$db;

        if (empty($field_ids)) {
            return array();
        }

        $field_id_str = implode(",", $field_ids);

        // get the overridden settings
        $db->query("
            SELECT fts.field_type_id, fs.field_id, fts.field_setting_identifier, fs.setting_value
            FROM   {PREFIX}field_type_settings fts, {PREFIX}field_settings fs
            WHERE  fts.setting_id = fs.setting_id AND
                   fs.field_id IN ($field_id_str)
            ORDER BY fs.field_id
        ");
        $db->execute();

        $overridden_settings = array();
        foreach ($db->fetchAll() as $row) {
            $overridden_settings[$row["field_id"]][$row["field_setting_identifier"]] = $row["setting_value"];
        }

        // now figure out what field_type_ids we're concerned about
        $relevant_field_type_ids = array();
        $field_id_to_field_type_id_map = array();
        foreach ($form_fields as $field_info) {
            if (!in_array($field_info["field_id"], $field_ids)) {
                continue;
            }
            if (!in_array($field_info["field_type_id"], $relevant_field_type_ids)) {
                $relevant_field_type_ids[] = $field_info["field_type_id"];
            }

            $field_id_to_field_type_id_map[$field_info["field_id"]] = $field_info["field_type_id"];
        }

        // this returns ALL the default field type settings. The function is "dumb": it doesn't evaluate
        // any of the dynamic default values - that's done below
        $default_field_type_settings = FieldTypes::getFieldTypeSettings($relevant_field_type_ids);

        // now overlay the two and return all field settings for all fields
        $results = array();
        foreach ($field_ids as $field_id) {
            $results[$field_id] = array();

            if (!isset($field_id_to_field_type_id_map[$field_id]) || !isset($default_field_type_settings[$field_id_to_field_type_id_map[$field_id]])) {
                continue;
            }

            $field_type_settings = $default_field_type_settings[$field_id_to_field_type_id_map[$field_id]];
            foreach ($field_type_settings as $setting_info) {
                $identifier         = $setting_info["field_setting_identifier"];
                $default_value_type = $setting_info["default_value_type"];
                if ($default_value_type == "static") {
                    $value = $setting_info["default_value"];
                } else {
                    $parts = explode(",", $setting_info["default_value"]);

                    // dynamic setting values should ALWAYS be of the form "setting_name,module_folder/'core'". If they're
                    // not, just ignore it
                    if (count($parts) != 2) {
                        $value = "";
                    } else {
                        $value = Settings::get($parts[0], $parts[1]);
                    }
                }

                if (isset($overridden_settings[$field_id]) && isset($overridden_settings[$field_id][$identifier])) {
                    $value = $overridden_settings[$field_id][$identifier];
                }

                $results[$field_id][$identifier] = $value;
            }
        }

        return $results;
    }


    /**
     * This is used on the Submission Listing page to provide the default value for the date range field, which
     * appears when a user chooses a date to search on.
     *
     * @param string $choice
     * @return array a hash with the two keys:
     *                 "default_date_field_search_value": the default value to show. This depends on what they
     *                       selected on the Settings -> Main tab field.
     *                 "date_field_search_js_format": the format to pass to the jQuery date range picker
     */
    public static function getDefaultDateFieldSearchValue($choice)
    {
        $LANG = Core::$L;
        $searchFormDateFieldFormat = Core::getSearchFormDateFieldFormat();

        if ($searchFormDateFieldFormat == "d/m/y") {
            $php_date_format = "j/n/Y";
            $date_field_search_js_format = "d/m/yy";
        } else {
            $php_date_format = "n/j/Y";
            $date_field_search_js_format = "m/d/yy";
        }

        $value = "";
        switch ($choice) {
            case "none":
                $value = $LANG["phrase_select_date"];
                break;
            case "today":
                $value = date($php_date_format);
                break;
            case "last_7_days":
                $now  = date("U");
                $then = $now - (60 * 60 * 24 * 7);
                $value = date($php_date_format, $then) . " - " . date($php_date_format, $now);
                break;
            case "month_to_date":
                $current_month = date("n");
                $current_year  = date("Y");
                if ($searchFormDateFieldFormat == "d/m/y") {
                    $value = "1/$current_month/$current_year - " . date($php_date_format);
                } else {
                    $value = "$current_month/1/$current_year - " . date($php_date_format);
                }
                break;
            case "year_to_date":
                $current_year  = date("Y");
                $value = "1/1/$current_year - " . date($php_date_format);
                break;
            case "previous_month":
                $current_month = date("n");
                $previous_month = ($current_month == 1) ? 12 : $current_month-1;
                $current_year  = date("Y");
                $mid_previous_month = mktime(0, 0, 0, $previous_month, 15, $current_year);
                $num_days_in_last_month = date("t", $mid_previous_month);
                if ($searchFormDateFieldFormat == "d/m/y") {
                    $value = "1/$previous_month/$current_year - $num_days_in_last_month/$previous_month/$current_year";
                } else {
                    $value = "$previous_month/1/$current_year - $previous_month/$num_days_in_last_month/$current_year";
                }
                break;
        }

        return array(
            "default_date_field_search_value" => $value,
            "date_field_search_js_format"     => $date_field_search_js_format
        );
    }


    /**
     * Returns a list of field type IDs for file fields.
     * @return array $field_type_ids
     */
    public static function getFileFieldTypeIds()
    {
        $db = Core::$db;

        $db->query("
            SELECT field_type_id
            FROM {PREFIX}field_types
            WHERE is_file_field = 'yes'
        ");
        $db->execute();

        $field_type_ids = array();
        foreach ($db->fetchAll() as $row) {
            $field_type_ids[] = $row["field_type_id"];
        }

        return $field_type_ids;
    }


    /**
     * Returns a list of field type IDs for date field types
     * @return array $field_type_ids
     *
     * Not used.
     */
//    public static function ft_get_date_field_type_ids()
//    {
//        global $g_table_prefix;
//
//        $query = mysql_query("SELECT field_type_id FROM {$g_table_prefix}field_types WHERE is_date_field = 'yes'");
//        $field_type_ids = array();
//        while ($row = mysql_fetch_assoc($query))
//        {
//            $field_type_ids[] = $row["field_type_id"];
//        }
//
//        return $field_type_ids;
//    }


    /**
     * This should be the one and only place that actually generates the content for a field for it
     * to be Viewed. This is used on the Submission Listing page, Edit Submission page (for viewable,
     * non-editable fields), in the Export Manager, in the Email Templates, and anywhere else that needs
     * to output the content of a field.
     *
     *    This function is the main source of slowness in 2.1.0. I'll be investigating ways to speed it up
     *    in the Beta.
     *
     * @param array $params a hash with the following:
     *              REQUIRED VALUES:
     *                form_id
     *                submission_id
     *                field_info - a hash containing details of the field:
     *                   REQUIRED:
     *                     field_id
     *                     field_type_id
     *                     col_name
     *                     field_name
     *                     settings - all extended settings defined for the field
     *                   OPTIONAL:
     *                     anything else you want
     *                field_types - all, or any that are relevant. But it should be an array, anyway
     *                value - the actual value stored in the field
     *                settings - (from ft_get_settings())
     * @return string
     */
    public static function generateViewableField($params)
    {
        global $LANG, $g_root_url, $g_root_dir, $g_multi_val_delimiter, $g_cache;

        // REQUIRED
        $form_id       = $params["form_id"];
        $submission_id = $params["submission_id"];
        $field_info    = $params["field_info"];
        $field_types   = $params["field_types"];
        $value         = $params["value"];
        $settings      = $params["settings"];
        $context       = $params["context"];

        // loop through the field types and store the one we're interested in in $field_type_info
        $field_type_info = array();
        foreach ($field_types as $curr_field_type) {
            if ($field_info["field_type_id"] == $curr_field_type["field_type_id"]) {
                $field_type_info = $curr_field_type;
                break;
            }
        }

        $markup_with_placeholders = trim($field_type_info["view_field_smarty_markup"]);
        $field_settings = $field_info["field_settings"];

        $output = "";
        if ($field_type_info["view_field_rendering_type"] == "none" || empty($markup_with_placeholders)) {
            $output = $value;
        } else {
            $account_info = isset($_SESSION["ft"]["account"]) ? $_SESSION["ft"]["account"] : array();

            // now construct all available placeholders
            $placeholders = array(
                "FORM_ID"       => $form_id,
                "SUBMISSION_ID" => $submission_id,
                "FIELD_ID"      => $field_info["field_id"],
                "NAME"          => $field_info["field_name"],
                "COLNAME"       => $field_info["col_name"],
                "VALUE"         => $value,
                "SETTINGS"      => $settings,
                "CONTEXTPAGE"   => $context,
                "ACCOUNT_INFO"  => $account_info,
                "g_root_url"    => $g_root_url,
                "g_root_dir"    => $g_root_dir,
                "g_multi_val_delimiter" => $g_multi_val_delimiter
            );

            // add in all field type settings and their replacements
            foreach ($field_type_info["settings"] as $setting_info) {
                $curr_setting_id         = $setting_info["setting_id"];
                $curr_setting_field_type = $setting_info["field_type"];
                $default_value_type      = $setting_info["default_value_type"];
                $value                   = $setting_info["default_value"];
                $identifier              = $setting_info["field_setting_identifier"];

                if (isset($field_settings) && !empty($field_settings)) {
                    for ($i=0; $i<count($field_settings); $i++) {
                        while (list($setting_id, $setting_value) = each($field_settings[$i])) {
                            if ($setting_id == $curr_setting_id) {
                                $value = $setting_value;
                                break;
                            }
                        }
                        reset($field_settings);
                    }
                }

                // next, if the setting is dynamic, convert the stored value
                if ($default_value_type == "dynamic") {
                    // dynamic setting values should ALWAYS be of the form "setting_name,module_folder/'core'". If they're not, just ignore it
                    $parts = explode(",", $value);
                    if (count($parts) == 2) {
                        $dynamic_setting_str = $value; // "setting_name,module_folder/'core'"
                        if (!array_key_exists("dynamic_settings", $g_cache)) {
                            $g_cache["dynamic_settings"] = array();
                        }
                        if (array_key_exists($dynamic_setting_str, $g_cache["dynamic_settings"])) {
                            $value = $g_cache["dynamic_settings"][$dynamic_setting_str];
                        } else {
                            $value = Settings::get($parts[0], $parts[1]);
                            $g_cache["dynamic_settings"][$dynamic_setting_str] = $value;
                        }
                    }
                }

                // if this setting type is a dropdown list and $value is non-empty, get the option list
                if ($curr_setting_field_type == "option_list_or_form_field" && !empty($value)) {
                    if (preg_match("/form_field:/", $value)) {
                        $value = ft_get_mapped_form_field_data($value);
                    } else {
                        $option_list_id = $value;

                        if (!array_key_exists("option_lists", $g_cache)) {
                            $g_cache["option_lists"] = array();
                        }
                        if (array_key_exists($option_list_id, $g_cache["option_lists"])) {
                            $value = $g_cache["option_lists"][$option_list_id];
                        } else {
                            $value = OptionLists::getOptionList($option_list_id);
                            $g_cache["option_lists"][$option_list_id] = $value;
                        }
                    }
                }

                $placeholders[$identifier] = $value;
            }

            if ($field_type_info["view_field_rendering_type"] == "php") {
                $php_function = $field_type_info["view_field_php_function"];

                // if this is a module, include the module's library.php file so we have access to the function
                if ($field_type_info["view_field_php_function_source"] != "core" && is_numeric($field_type_info["view_field_php_function_source"])) {
                    $module_folder = Modules::getModuleFolderFromModuleId($field_type_info["view_field_php_function_source"]);
                    @include_once("$g_root_dir/modules/$module_folder/library.php");
                }

                if (function_exists($php_function)) {
                    $output = $php_function($placeholders);
                }
            } else {
                $output = General::evalSmartyString($markup_with_placeholders, $placeholders);
            }
        }

        return $output;
    }


    public static function displayFieldTypeDate($placeholders)
    {
        if (empty($placeholders["VALUE"])) {
            return "";
        }

        $tzo = "";
        if ($placeholders["apply_timezone_offset"] == "yes" && isset($placeholders["ACCOUNT_INFO"]["timezone_offset"])) {
            $tzo = $placeholders["ACCOUNT_INFO"]["timezone_offset"];
        }

        switch ($placeholders["display_format"]) {
            case "yy-mm-dd":
                $php_format = "Y-m-d";
                break;
            case "dd/mm/yy":
                $php_format = "d/m/Y";
                break;
            case "mm/dd/yy":
                $php_format = "m/d/Y";
                break;
            case "M d, yy":
                $php_format = "M j, Y";
                break;
            case "MM d, yy":
                $php_format = "F j, Y";
                break;
            case "D M d, yy":
                $php_format = "D M j, Y";
                break;
            case "DD, MM d, yy":
                $php_format = "l M j, Y";
                break;
            case "dd. mm. yy.":
                $php_format = "d. m. Y.";
                break;
            case "datetime:dd/mm/yy|h:mm TT|ampm`true":
                $php_format = "d/m/Y g:i A";
                break;
            case "datetime:mm/dd/yy|h:mm TT|ampm`true":
                $php_format = "m/d/Y g:i A";
                break;
            case "datetime:yy-mm-dd|h:mm TT|ampm`true":
                $php_format = "Y-m-d g:i A";
                break;
            case "datetime:yy-mm-dd|hh:mm":
                $php_format = "Y-m-d H:i";
                break;
            case "datetime:yy-mm-dd|hh:mm:ss|showSecond`true":
                $php_format = "Y-m-d H:i:s";
                break;
            case "datetime:dd. mm. yy.|hh:mm":
                $php_format = "d. m. Y. H:i";
                break;

            default:
                $php_format = "";
                break;
        }

        return ft_get_date($tzo, $placeholders["VALUE"], $php_format);
    }


    public static function displayFieldTypeRadios($placeholders)
    {
        // if this isn't assigned to an Option List / form field, ignore the sucker
        if (empty($placeholders["contents"])) {
            return "";
        }

        $output = "";
        foreach ($placeholders["contents"]["options"] as $curr_group_info) {
            $options    = $curr_group_info["options"];

            foreach ($options as $option_info) {
                if ($placeholders["VALUE"] == $option_info["option_value"]) {
                    $output = $option_info["option_name"];
                    break;
                }
            }
        }

        return $output;
    }


    public static function displayFieldTypeCheckboxes($placeholders)
    {
        // if this isn't assigned to an Option List / form field, ignore it!
        if (empty($placeholders["contents"])) {
            return "";
        }

        $multi_val_delimiter = $placeholders["g_multi_val_delimiter"];
        $vals = explode($multi_val_delimiter, $placeholders["VALUE"]);

        $output = "";
        $is_first = true;
        foreach ($placeholders["contents"]["options"] as $curr_group_info) {
            $options = $curr_group_info["options"];
            foreach ($options as $option_info) {
                if (in_array($option_info["option_value"], $vals)) {
                    if (!$is_first) {
                        $output .= $multi_val_delimiter;
                    }
                    $output .= $option_info["option_name"];
                    $is_first = false;
                }
            }
        }

        return $output;
    }


    public static function displayFieldTypeDropdown($placeholders)
    {
        if (empty($placeholders["contents"])) {
            return "";
        }

        $output = "";
        foreach ($placeholders["contents"]["options"] as $curr_group_info) {
            $options = $curr_group_info["options"];
            foreach ($options as $option_info) {
                if ($placeholders["VALUE"] == $option_info["option_value"]) {
                    // the extra check for $option_info not being empty was added in 2.2.7. This is because
                    // there was an old bug preventing the value being displayed properly. But by fixing it,
                    // default dropdown values like "please select" suddenly got shown. I think it's reasonable
                    // to equate an empty string value with nothing...
                    if (!empty($option_info["option_value"])) {
                        $output = $option_info["option_name"];
                    }
                    break;
                }
            }
        }

        return $output;
    }


    public static function displayFieldTypeMultiSelectDropdown($placeholders)
    {
        // if this isn't assigned to an Option List / form field, ignore it!
        if (empty($placeholders["contents"])) {
            return "";
        }

        $multi_val_delimiter = $placeholders["g_multi_val_delimiter"];
        $vals = explode($multi_val_delimiter, $placeholders["VALUE"]);

        $output = "";
        $is_first = true;
        foreach ($placeholders["contents"]["options"] as $curr_group_info) {
            $options = $curr_group_info["options"];

            foreach ($options as $option_info) {
                if (in_array($option_info["option_value"], $vals)) {
                    if (!$is_first) {
                        $output .= $multi_val_delimiter;
                    }
                    $output .= $option_info["option_name"];
                    $is_first = false;
                }
            }
        }

        return $output;
    }


    public static function displayFieldTypePhoneNumber($placeholders)
    {
        $phone_number_format = $placeholders["phone_number_format"];
        $values = explode("|", $placeholders["VALUE"]);

        $pieces = preg_split("/(x+)/", $phone_number_format, 0, PREG_SPLIT_DELIM_CAPTURE);
        $counter = 1;
        $output = "";
        $has_content = false;
        foreach ($pieces as $piece) {
            if (empty($piece)) {
                continue;
            }
            if ($piece[0] == "x") {
                $value = (isset($values[$counter-1])) ? $values[$counter-1] : "";
                $output .= $value;
                if (!empty($value)) {
                    $has_content = true;
                }
                $counter++;
            } else {
                $output .= $piece;
            }
        }

        if (!empty($output) && $has_content) {
            return $output;
        } else {
            return "";
        }
    }


    public static function displayFieldTypeCodeMarkup($placeholders)
    {
        if ($placeholders["CONTEXTPAGE"] == "edit_submission")
        {
            $code_markup = $placeholders["code_markup"];
            $value       = $placeholders["VALUE"];
            $height      = $placeholders["height"];
            $g_root_url  = $placeholders["g_root_url"];

            // TODO name not defined!

            $output =<<< END
	<textarea id="{$name}_id" name="{$name}">{$value}</textarea>
	<script>
	var code_mirror_{$name} = new CodeMirror.fromTextArea("{$name}_id", {
		height:   "{$height}px",
		path:     "{$g_root_url}/global/codemirror/js/",
		readOnly: true,
		{if $code_markup == "HTML" || $code_markup == "XML"}
			parserfile: ["parsexml.js"],
			stylesheet: "{$g_root_url}/global/codemirror/css/xmlcolors.css"
		{elseif $code_markup == "CSS"}
			parserfile: ["parsecss.js"],
			stylesheet: "{$g_root_url}/global/codemirror/css/csscolors.css"
		{elseif $code_markup == "JavaScript"}
			parserfile: ["tokenizejavascript.js", "parsejavascript.js"],
			stylesheet: "{$g_root_url}/global/codemirror/css/jscolors.css"
		{/if}
	});
	</script>
END;
        } else {
            $output = strip_tags($placeholders["VALUE"]);
        }

        return $output;
    }


    /**
     * Used when updating a field. This is passed those field that have just had their field type changed. It figures
     * out what values
     *
     * @param array $field_type_map
     * @param string $field_type_settings_shared_characteristics
     * @param integer $field_id
     * @param integer $new_field_type_id
     * @param integer $old_field_type_id
     */
    public static function getSharedFieldSettingInfo($field_type_map, $field_type_settings_shared_characteristics, $field_id, $new_field_type_id, $old_field_type_id)
    {
        $new_field_type_identifier = $field_type_map[$new_field_type_id];
        $old_field_type_identifier = $field_type_map[$old_field_type_id];

        $groups = explode("|", $field_type_settings_shared_characteristics);
        $return_info = array();
        foreach ($groups as $group_info) {
            list($group_name, $vals) = explode(":", $group_info);

            $pairs = explode("`", $vals);
            $settings = array();
            foreach ($pairs as $str) {
                list($field_type_identifier, $setting_identifier) = explode(",", $str);
                $settings[$field_type_identifier] = $setting_identifier;
            }

            $shared_field_types = array_keys($settings);
            if (!in_array($new_field_type_identifier, $shared_field_types) || !in_array($old_field_type_identifier, $shared_field_types)) {
                continue;
            }

            $old_setting_id = self::getFieldTypeSettingIdByIdentifier($old_field_type_id, $settings[$new_field_type_identifier]);
            $new_setting_id = self::getFieldTypeSettingIdByIdentifier($new_field_type_id, $settings[$old_field_type_identifier]);

            $old_setting_value = ft_get_field_setting($field_id, $old_setting_id);
            $return_info[] = array(
                "field_id"       => $field_id,
                "old_setting_id" => $old_setting_id,
                "new_setting_id" => $new_setting_id,
                "setting_value"  => $old_setting_value
            );
        }

        return $return_info;
    }


    /**
     * This is used exclusively on the Edit Forms -> fields tab. It returns a JS version of the shared characteristics
     * information for use by the page. The JS it returns in an anonymous JS object of the following form:
     *   {
     *     s(setting ID): array(characteristic IDs),
     *     ...
     *   }
     *
     * "Characteristic ID" is a made-up number for the sake of the use-case. We just need a way to recognize the shared
     * characteristics - that's what it does.
     *
     * @return string
     */
    public static function getFieldTypeSettingSharedCharacteristicsJs()
    {
        $field_type_settings_shared_characteristics = Settings::get("field_type_settings_shared_characteristics");
        $info = FieldTypes::getFieldTypeAndSettingInfo();
        $field_type_id_to_identifier = $info["field_type_id_to_identifier"];
        $field_identifier_to_id = array_flip($field_type_id_to_identifier);

        $groups = explode("|", $field_type_settings_shared_characteristics);
        $return_info = array();

        // this is what we're trying to generate: a hash of setting id => array( characteristic IDs )
        // The �characteristic ID� is a new (temporary) number for characteristic. In every situation that I can
        // think of, the value array will contain a single entry (why would a setting be mapped to multiple
        // characteristics?). However, the interface doesn't limit it. To be safe, I�ll stash it in an array.
        $setting_ids_to_characteristic_ids = array();

        $characteristic_id = 1;
        foreach ($groups as $group_info) {
            list($group_name, $vals) = explode(":", $group_info);

            $pairs = explode("`", $vals);
            $settings = array();
            foreach ($pairs as $str) {
                // we need to do a little legwork here to actually find the setting ID. The problem is that many
                // field types reference fields with the same setting identifier (it's only required to be unique within the
                // field type - not ALL field types).
                list($field_type_identifier, $setting_identifier) = explode(",", $str);

                // the shared characteristic settings may reference uninstalled modules
                if (!array_key_exists($field_type_identifier, $field_identifier_to_id)) {
                    continue;
                }

                $field_type_id = $field_identifier_to_id[$field_type_identifier];
                $all_field_type_setting_ids = $info["field_type_ids_to_setting_ids"][$field_type_id];

                // loop through all the settings for this field type and locate the one we're interested in
                foreach ($all_field_type_setting_ids as $setting_id) {
                    if ($info["setting_id_to_identifier"][$setting_id] != $setting_identifier) {
                        continue;
                    }
                    if (!array_key_exists($setting_id, $setting_ids_to_characteristic_ids)) {
                        $setting_ids_to_characteristic_ids[$setting_id] = array();
                    }
                    $setting_ids_to_characteristic_ids[$setting_id][] = $characteristic_id;
                }
            }

            $characteristic_id++;
        }

        // now convert the info into a simple JS object. We could have done it above, but this keeps it simple.
        $js_lines = array();
        while (list($setting_id, $characteristic_ids) = each($setting_ids_to_characteristic_ids)) {
            $js_lines[] = "s{$setting_id}:[" . implode(",", $characteristic_ids) . "]";
        }
        $js = "{" . implode(",", $js_lines) . "}";

        return $js;
    }


    /**
     * A little tricky to name. We often need the key info about the field type and their settings (i.e. IDs and names)
     * in different ways. This function returns the info in different data structures. The top level structure returned
     * is a hash. You can pick and choose what info you want. Since it's all generated with a single SQL query, it's much
     * faster to use this than separate functions.
     *
     * Note: this function returns a superset of getFieldTypeIdToIdentifierMap(). If you need to access the settings
     * as well as the field type info, chances are this will be a better candidate.
     *
     * @return array
     */
    public static function getFieldTypeAndSettingInfo()
    {
        $db = Core::$db;

        $db->query("
            SELECT ft.field_type_id, ft.field_type_name, ft.field_type_identifier, fts.*
            FROM {PREFIX}field_types ft
            LEFT JOIN {PREFIX}field_type_settings fts ON (ft.field_type_id = fts.field_type_id)
        ");
        $db->execute();

        $field_type_id_to_identifier   = array();
        $field_type_ids_to_setting_ids = array();
        $setting_id_to_identifier      = array();
        foreach ($db->fetchAll() as $row) {
            $field_type_id = $row["field_type_id"];
            $setting_id    = $row["setting_id"];

            if (!array_key_exists($field_type_id, $field_type_id_to_identifier)) {
                $field_type_id_to_identifier[$field_type_id] = $row["field_type_identifier"];
            }
            if (!array_key_exists($field_type_id, $field_type_ids_to_setting_ids)) {
                $field_type_ids_to_setting_ids[$field_type_id] = array();
            }
            $field_type_ids_to_setting_ids[$field_type_id][] = $setting_id;

            if (!array_key_exists($setting_id, $setting_id_to_identifier)) {
                $setting_id_to_identifier[$setting_id] = $row["field_setting_identifier"];
            }
        }

        return array(
            "field_type_id_to_identifier"   => $field_type_id_to_identifier,
            "field_type_ids_to_setting_ids" => $field_type_ids_to_setting_ids,
            "setting_id_to_identifier"      => $setting_id_to_identifier
        );
    }


    /**
     * Returns all validation rules for a field type.
     *
     * @param $field_type_id
     */
    public static function ft_get_field_type_validation_rules($field_type_id) {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}field_type_validation_rules
            WHERE  field_type_id = :field_type_id
            ORDER BY list_order
        ");
        $db->bind("field_type_id", $field_type_id);
        $db->execute();

        return $db->fetchAll();
    }


    /**
     * Return information about the field types in the database. To provide a little re-usability, the two
     * params let you choose whether or not to return the field types AND their settings or just
     * the field types, and whether or not you want to limit the results to specific field type IDs.
     *
     * @param array $return_settings
     * @param array $field_type_ids
     * @return array
     */
    public static function get($return_settings = false, $field_type_ids = array())
    {
        $db = Core::$db;

        if (!empty($field_type_ids)) {
            $field_type_id_str = implode(",", $field_type_ids);
            $db->query("
                SELECT *, g.list_order as group_list_order, ft.list_order as field_type_list_order
                FROM   {PREFIX}field_types ft, {PREFIX}list_groups g
                WHERE  g.group_type = :field_types AND
                       ft.group_id = g.group_id AND 
                       ft.field_type_id IN ($field_type_id_str)
                ORDER BY g.list_order, ft.list_order
            ");
        } else {
            $db->query("
                SELECT *, g.list_order as group_list_order, ft.list_order as field_type_list_order
                FROM   {PREFIX}field_types ft, {PREFIX}list_groups g
                WHERE  g.group_type = :field_types AND
                       ft.group_id = g.group_id
                ORDER BY g.list_order, ft.list_order
            ");
        }
        $db->bind(":field_types", "field_types");
        $db->execute();
        $results = $db->fetchAll();

        $field_types = array();
        foreach ($results as $row) {
            if ($return_settings) {
                $curr_field_type_id = $row["field_type_id"];
                $row["settings"] = FieldTypes::getFieldTypeSettings($curr_field_type_id, false);
            }
            $field_types[] = $row;
        }

        return $field_types;
    }


    public static function getFieldTypeByIdentifier($identifier)
    {
        $db = Core::$db;
        $db->query("
            SELECT *
            FROM   {PREFIX}field_types
            WHERE  field_type_identifier = :identifier
        ");
        $db->bind(":identifier", $identifier);
        $db->execute();
        $info = $db->fetch();

        if (!empty($info)) {
            $field_type_id = $info["field_type_id"];
            $info["settings"] = FieldTypes::getFieldTypeSettings($field_type_id);
        }

        return $info;
    }

}
