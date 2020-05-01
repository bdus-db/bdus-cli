<?php
namespace mngProject;

/**
 *
 * Validates configuration files for project, both local or remote
 */
class Validate
{
  private static $cache = [];

  /**
   * Runs all validation
   * @param  string  $path2cfg path to configuration folder
   * @param  boolean $echo     if true success messages will be shown
   * @return true            if OK
   * @throws Exception       if error
   */
  public static function all($path2cfg, $echo = false){
    self::filesExist($path2cfg, $echo);
    self::appData($path2cfg, $echo);
    self::tables($path2cfg, $echo);
    return true;
  }

  /**
   * Utility method to check if a field is present in a table'sconfiguration file
   * @param  string $path2cfg path to configuration folder
   * @param  string $tb       table name to look in
   * @param  string $fld      field name to look for
   * @return boolen           true if found, false if not
   */
  private static function tbHasfld($path2cfg, $tb, $fld){
    if ($tb === 'files') {
      return \in_array($fld, ['id', 'creator', 'ext', 'keywords', 'description', 'printable', 'filename']);
    }
    if (!@self::$cache[$tb]){
      self::$cache[$tb] = u::getJson("{$path2cfg}/{$tb}.json");
    }

    $found = false;
    foreach (self::$cache[$tb] as $el) {
      if ($fld === $el['name']){
        $found = true;
      }
    }
    return $found;
  }



  private static function tables($path2cfg, $echo = false){
    u::echo("Checking {$path2cfg}/tables.json", $echo);

    $tables = u::getJson("{$path2cfg}/tables.json");

    foreach($tables['tables'] as $t) {
      self::tablesStructure($t, $path2cfg, $echo);
      self::oneTableStructure($t['name'], $path2cfg, $echo);
    }
    u::echo("{$path2cfg}/tables.json: OK", $echo);

  }

  /**
   * [oneTableStructure description]
   *    Checks for name, label, type
   *    Checks if type is one of: text, date, long_text, select, combo_select, multi_select, boolean, slider
   *    Checks if select, combo_select, multi_select have a dictionary
   *    Checks if check is one or more of: int, email, no_dupl, not_empty, range, regex
   *    Checks if check is has check regex and has pattern
   *    Checks if  get_values_from_tb point to valid data
   * @param  [type]  $t        [description]
   * @param  [type]  $path2cfg [description]
   * @param  boolean $echo     [description]
   * @return [type]            [description]
   */
  private function oneTableStructure($t, $path2cfg, $echo = false){
    u::echo("Checking configuration for {$t}", $echo);

    $t = preg_replace('/([a-z]+)__/', '', $t);
    if ($t === 'files' || $t === 'geodata'){
      return;
    }
    $table_array = u::getJson("{$path2cfg}/{$t}.json");

    foreach ($table_array as $e) {

      // Checks for name, label, type
      foreach (['name', 'label', 'type'] as $k) {
        if (!$e[$k]){
          throw new \Exception("Missing required index {$k} for {$path2cfg}/{$t}.json");
        }
      }

      // Checks if type is one of: text, date, long_text, select, combo_select, multi_select, boolean, slider
      if (!in_array($e['type'], ['text', 'date', 'long_text', 'select', 'combo_select', 'multi_select', 'boolean', 'slider'])){
        throw new \Exception("Invalid type {$e['type']} for {$t}.{$e['name']}");
      }

      // Checks if select, combo_select, multi_select have a dictionary
      if (
        in_array($e['type'], ['select', 'combo_select', 'multi_select'])
        &&
        (
          !@$e['vocabulary_set'] &&
          !@$e['get_values_from_tb'] &&
          !@$e['id_from_tb']
        )
      ){
        if ($e['multi_select'] && !$e['vocabulary_set'])
        throw new \Exception("Type {$e['type']} of {$t}.{$e['name']} is missing a dictionary");
      }

      // Checks if check is one or more of: int, email, no_dupl, not_empty, range, regex
      if (@$e['check']){
        if (!is_array($e['check'])) {
          throw new \Exception("Some check is active on {$t}.{$e['name']} but is is not an array");
        }
        foreach ($e['check'] as $c) {
          if (!in_array($c, ['int', 'email', 'no_dupl', 'not_empty', 'range', 'regex'])){
            throw new \Exception("Invalid check type {$c} on {$t}.{$e['name']}");
          }
        }

        // Checks if check is has check regex and has pattern
        if (in_array('regex', $e['check']) && !@$e['pattern']){
          throw new \Exception("Field {$t}.{$e['name']} has a regex check rule, but no pattern is defined");
        }
      }

      // Checks if  get_values_from_tb point to valid data
      if (@$e['get_values_from_tb']) {
        $p = explode(':', $e['get_values_from_tb']);
        $p[0] = preg_replace('/([a-z]+)__/', '', $p[0]);
        if (!u::fileExists("{$path2cfg}/{$p[0]}.json")){
          throw new \Exception("Missing configuration file {$path2cfg}/{$p[0]}.json for table mentioned in {$t}.{$e['name']} get_values_from_tb value");
        }

        if (!self::tbHasfld($path2cfg, $p[0], $p[1])){
          throw new \Exception("Table {$p[0]} does not have a field {$p[1]} as mentioned in {$t}.{$e['name']} get_values_from_tb value");
        }
      }
    }

    u::echo("  Configuration for {$t}: OK", $echo);

  }

  /**
   * Validates structure and consistency of each element of tables.json
   *    Checks for name, label in all tables
   *    Checks for order, id_field, preview in non plugin tables
   *    If preview is set, it must be an array
   *    If preview is set, they must be valid fields
   *    Plugin tables must exist
   *    Links: other_tb must exist
   *    Links: my must be valid fields
   *    Links: other must be valid fields
   *
   * @param  string  $t        element to validate
   * @param  string  $path2cfg path to config folder
   * @param  boolean $echo     if true feedback will be echoed
   * @return true            if OK
   * @throws Exception      if error
   */
  private static function tablesStructure($t, $path2cfg, $echo = false){

    // name
    if(!$t['name']){
      throw new \Exception("Required key `name` in tables.json is missing");
    }

    u::echo("Checking {$t['name']}", $echo);


    $stripped_table = \preg_replace('/([a-z]+)__/', '', $t['name']);

    // label
    if(!$t['label']){
      throw new \Exception("Required key `label` in tables.json ({$t['name']}) is missing");
    }

    // order (non plugin tables)
    if(!@$t['is_plugin'] && !$t['order']){
      throw new \Exception("Required key `order` in tables.json ({$t['name']}) is missing");
    }

    // plugin tables finishes here!
    if(@$t['is_plugin']){
      u::echo("  Plugin table {$t['name']}: OK", $echo);
      return true;
    }

    // id_field (non plugin tables)
    if(!$t['id_field']){
      throw new \Exception("Required key `id_field` in tables.json ({$t['name']}) is missing");
    }

    // If preview is set, it must be an array
    if( !$t['preview'] || !is_array($t['preview']) ){
      throw new \Exception("Required key `preview` in tables.json ({$t['name']}) is missing");
    }

    // If preview is set, they must be valid fields
    foreach ($t['preview'] as $p) {
      if (!self::tbHasfld($path2cfg, $stripped_table, $p)){
        throw new \Exception("Preview field {$p} of table {$t['name']} reported in tables.json was not found in fields list in {$stripped_table}.json");
      }
    }
    // Plugin tables must exist
    if (@$t['plugin']){
      if (!is_array($t['plugin'])){
        throw new \Exception("Plugin tablelist of table {$t['name']} reported in tables.json is not a valid list");
      }
      foreach ($t['plugin'] as $p) {
        $clean_p = \preg_replace('/([a-z]+)__/', '', $p);
        if(!u::fileExists("{$path2cfg}/{$clean_p}.json")){
          throw new \Exception("Missing configuration file {$path2cfg}/{$clean_p}.json required in tables.json");
        }
      }
    }
    // foreach link
    if (@$t['link']){
      if (!is_array($t['link'])){
        throw new \Exception("Error in link formatting for table {$t['name']} in tables.json");
      }
      foreach ($t['link'] as $l) {

        //    other_tb must exist
        if (!$l['other_tb']){
          throw new \Exception("Missing index other_tb for link in table {$t['name']} in tables.json");
        }
        if (!$l['fld'] || !is_array($l['fld'])){
          throw new \Exception("Missing or not valid index fld for link in table {$t['name']} in tables.json");
        }
        $ltb = preg_replace('/([a-z]+)__/', '', $l['other_tb']);
        if(!u::fileExists("{$path2cfg}/{$ltb}.json")){
          throw new \Exception("Missing configuration file {$path2cfg}/{$ltb}.json required in tables.json");
        }

        //    foreach fld
        foreach ($l['fld'] as $fld) {
          //      my must exist,
          if(!self::tbHasfld($path2cfg, $stripped_table, $fld['my'])){
            throw new \Exception("Table {$stripped_table} does not have a field named {$fld['my']} as stated in links in tables.json");
          }
          //      other must exist
          if(!self::tbHasfld($path2cfg, $ltb, $fld['other'])){
            throw new \Exception("Table {$ltb} does not have a field named {$fld['other']} as stated in links in tables.json");
          }
        }
      }
    }

    // backlinks must be valid
    if (@$t['backlinks']){
      if (!is_array($t['backlinks'])){
        throw new \Exception("Backlinks are not well formatted for table {$t['name']} in tables.json");
      }
      foreach ($t['backlinks'] as $bl) {
        $p = explode(':', $bl);

        if(!u::fileExists($path2cfg. '/' . preg_replace('/([a-z]+)__/', '', $p[0]) . '.json' )){
          throw new \Exception("Missing configuration file for table {$p[0]} reported in backlinks of table {$t['name']} in tables.config");
        }

        if(!u::fileExists($path2cfg. '/' . preg_replace('/([a-z]+)__/', '', $p[1]) . '.json' )){
          throw new \Exception("Missing configuration file for table {$p[1]} reported in backlinks of table {$t['name']} in tables.config");
        }

        if (!self::tbHasfld(
          $path2cfg,
          preg_replace('/([a-z]+)__/', '', $p[1]),
          $p[2])
        ) {
          throw new \Exception("table {$p[1]} does not have a field named {$p[2]} as reported in backlinks of table {$t['name']} in tables.config");
        }
      }
    }
    u::echo("  Table {$t['name']}: OK", $echo);

  }

  /**
   * Checks if fundamental cfg files exist
   * @param  string $path2cfg path to cfg folder
   * @param  boolean $echo if true feedback will be echoed
   * @return true           If OK
   * @throws Exception      If error
   */
  private static function filesExist($path2cfg, $echo = false) {
    u::echo('Checking file existence', $echo);
    $c = [
      'app_data.json',
      'tables.json'
    ];
    foreach ($c as $f) {
      if(!u::fileExists("{$path2cfg}/{$f}")){
        throw new \Exception("File {$path2cfg}/{$f} does not exist!");
      }
      u::echo("  File {$path2cfg}/{$f}: OK", $echo);
    }
  }


  /**
   * Checks if app_data.json structure is valid
   * @param  string $path2cfg path to cfg folder
   * @param  boolean $echo if true feedback will be echoed
   * @return true           if OK
   * @throws Exception      If error
   */
  static function appData($path2cfg, $echo = false){
    u::echo('Checking app_data', $echo);
    $d = u::getJson("{$path2cfg}/app_data.json");
    foreach ([
      'lang',
      'name',
      'definition'
      ] as $must) {
      if(!$d[$must]){
        throw new \Exception("The required index `{$must}` of `{$path2cfg}/app_data.json` is missing", 1);
      }
    }
    u::echo('  app_data: OK', $echo);
    return true;
  }
}
