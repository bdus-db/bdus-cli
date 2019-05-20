<?php
namespace mngProject;



/**
 * Creates project folder from configuration files
 */
class Create
{
  private static $app = false;


  public static function all($path2cfg, $path2dest, $echo = false){
    u::echo("Start config validation", $echo);
    Validate::all($path2cfg, false);
    u::echo("  Config validation: ok", $echo);

    $app = self::getApp($path2cfg);
    $path2dest = rtrim($path2dest, '/') . '/' . $app;


    u::echo("Start create folders", $echo);
    self::createFolders($path2dest);
    u::echo("  Copy folders: ok", $echo);

    u::echo("Start copy config files", $echo);
    self::copyCfg($path2cfg, $path2dest);
    u::echo("  Copy config files: ok", $echo);

    u::echo("Start destination validation", $echo);
    Validate::all("{$path2dest}/cfg", true);
    u::echo("  Config destination: ok", $echo);

    u::echo("Start create missing files", $echo);
    self::createMissing($path2dest, $path2cfg);
    u::echo("  Create missing files: ok", $echo);

    u::echo("Start create system database", $echo);
    self::createSystemDb($path2cfg, $path2dest);
    u::echo("  Create system database: ok", $echo);

    u::echo("Start create user database", $echo);
    self::createUserDb($path2cfg, $path2dest);
    u::echo("  Create user database: ok", $echo);

  }

  private static function createUserDb($path2cfg, $path2dest){
    $app = self::getApp($path2cfg);
    // per ogni elemento in tables
    //    apri file di singole tabelle
    //    crea sql ed eseguilo
    $tables = u::getJson("{$path2cfg}/tables.json");

    foreach ($tables['tables'] as $t) {
      $n = str_replace($app . '__', '', $t['name']);
      $tb = u::getJson("{$path2cfg}/{$n}.json");
      $fields = [
        "id" => "id INTEGER PRIMARY KEY"
      ];
      if (@$t['is_plugin']){
        $fields['table_link'] = 'table_link TEXT';
        $fields['id_link'] = 'id_link TEXT';
      } else{
        $fields["creator"] = "creator TEXT";
      }

      foreach ($tb as $f) {
        if (in_array($f['name'], array_keys($fields))){
          continue;
        }
        $name = $f['name'];
        $type = @$name . ' ';
        $type .= @$f['db_type'] ?: 'TEXT';
        $type .= @$f['max'] ? " ({$f['max']}) " : '';
        $fields[$name] = $type;
      }

      $sql = "CREATE TABLE {$t['name']} (" .
        implode(", ", $fields).
      "); ";

      \R::exec($sql);
    }
  }

  private static function createSystemDb($path2cfg, $path2dest){
    $app = self::getApp($path2cfg);
    $db['charts'] = <<<EOD
CREATE TABLE {$app}__charts (
  `id`      INTEGER  PRIMARY KEY NOT NULL,
  `user_id` INTEGER,
  `name`    TEXT,
  `query`   TEXT,
  `date`    DATETIME
);
EOD;
    $db['charts'] = <<<EOD
CREATE TABLE {$app}__files (
  `id`          INTEGER PRIMARY KEY,
  `creator`     TEXT,
  `ext`         TEXT,
  `keywords`    TEXT,
  `description` TEXT,
  `printable`   INTEGER,
  `filename`    TEXT
);
EOD;
    $db['geodata'] = <<<EOD
CREATE TABLE {$app}__geodata (
    `id`           INTEGER PRIMARY KEY,
    `table_link`   TEXT    NOT NULL,
    `id_link`      INTEGER NOT NULL,
    `geometry`     TEXT    NOT NULL,
    `geo_el_elips` INTEGER,
    `geo_el_asl`   INTEGER
);
EOD;
    $db['queries'] = <<<EOD
CREATE TABLE {$app}__queries (
  `id`        INTEGER PRIMARY KEY,
  `user_id`   INTEGER,
  `date`      DATE,
  `name`      TEXT,
  `text`      TEXT,
  `table`     TEXT,
  `is_global` INTEGER
);
EOD;
    $db['rs'] = <<<EOD
CREATE TABLE {$app}__rs (
  `id`       INTEGER PRIMARY KEY,
  `tb`       TEXT,
  `first`    TEXT,
  `second`   TEXT,
  `relation` INTEGER
);
EOD;
    $db['userlinks'] = <<<EOD
CREATE TABLE {$app}__userlinks (
  `id`     INTEGER PRIMARY KEY NOT NULL,
  `tb_one` TEXT    NOT NULL,
  `id_one` INTEGER NOT NULL,
  `tb_two` TEXT    NOT NULL,
  `id_two` INTEGER NOT NULL,
  `sort`   INTEGER
);
EOD;
    $db['users'] = <<<EOD
CREATE TABLE {$app}__users (
  `id`        INTEGER PRIMARY KEY,
  `name`      TEXT,
  `email`     TEXT,
  `password`  TEXT,
  `privilege` INTEGER,
  `settings`  TEXT
);
EOD;
    $pwd = sha1('test');
    $db['user'] = <<<EOD
INSERT INTO {$app}__users
  ( `name`, `email`, `password`, `privilege` )
VALUES
  ('Test User', 'test@bradypus.net', '${pwd}', 1);
EOD;
    $db['vocabularies'] = <<<EOD
CREATE TABLE {$app}__vocabularies (
  `id`   INTEGER PRIMARY KEY,
  `voc`  TEXT,
  `def`  TEXT,
  `sort` INTEGER
);
EOD;

    foreach ($db as $t => $sql) {
      \R::exec($sql);
    }

  }

  private static function getApp($path2cfg){
    if (!self::$app){
      $app_data = u::getJson("{$path2cfg}/app_data.json");
      self::$app = $app_data['name'];
    }
    return self::$app;
  }

  private function copyCfg($path2cfg, $path2dest){
    $copy = [
      "app_data.json",
      "tables.json",
      "files.json",
      "geodata.json"
    ];

    $tables = u::getJson("{$path2cfg}/tables.json");

    foreach ($tables['tables'] as $t) {
      $n = str_replace(self::getApp($path2cfg) . '__', '', $t['name']);
      array_push($copy, "{$n}.json");
    }


    foreach ($copy as $f) {
      @copy("{$path2cfg}/{$f}", "{$path2dest}/cfg/{$f}");
      if (!file_exists("{$path2dest}/cfg/{$f}")){
        throw new \Exception("Cannot copy to {$path2dest}/cfg/{$f}");
      }
    }
  }

  /**
   * Create project main folder and subfolders and checks if created
   * @param  string $path2dest path to project folder to create
   * @return true            if OK
   * @throws Exceptions      if error
   */
  private function createFolders($path2dest){
    if (is_dir($path2dest)){
      throw new \Exception("Directory {$path2dest} exists. Delete and rerun");
    }
    @mkdir($path2dest);
    if (!is_dir($path2dest)){
      throw new \Exception("Cannot create directory {$path2dest}. Check permissions");
    }
    foreach ([
      'backups',
      'cfg',
      'db',
      'export',
      'files',
      'geodata',
      'templates',
      'tmp',
      'sessions'
      ] as $d) {
        @mkdir("{$path2dest}/{$d}");
        if (!is_dir($path2dest)){
          throw new \Exception("Cannot create directory {$path2dest}/{$d}. Check permissions");
        }
    }

  }

  private function createMissing($path2dest, $path2cfg){
    $create['cfg/geodata.json'] = [
        [
            "name" => "id",
            "label" => "ID",
            "type" => "text",
            "readonly" => "1",
            "hide" => "1"
        ],
        [
            "name" => "geometry",
            "label" => "Coordinates (WKT format)",
            "type" => "text"
        ]
    ];

    $create['cfg/files.json'] = [
      [
        "name" => "id",
        "label" => "ID",
        "type" => "text",
        "readonly" => true
      ], [
        "name" => "ext",
        "label" => "Extension",
        "type" => "text",
        "check" => [
          "not_empty"
        ],
        "readonly" => true
      ], [
        "name" => "filename",
        "label" => "Filename",
        "type" => "text",
        "check" => [
          "not_empty"
        ],
        "readonly" => true
      ], [
        "name" => "keywords",
        "label" => "Keywords",
        "type" => "text"
      ], [
        "name" => "description",
        "label" => "Description",
        "type" => "long_text"
      ], [
        "name" => "printable",
        "label" => "Printable",
        "type" => "boolean"
      ]
    ];

    foreach ($create as $k => $v) {
      if (!file_exists("{$path2dest}/{$k}")){
        @file_put_contents("{$path2dest}/{$k}", json_encode($v));
        if (!file_exists("{$path2dest}/{$k}")){
          throw new \Exception("Cannot create file {$path2dest}/{$k}");
        }
      }
    }

    @file_put_contents("{$path2dest}/welcome.html", "<h1>" . strtoupper(self::getApp($path2cfg)) . "</h1>\n\n<h3>A BraDypUS database</h3>");

    @touch("{$path2dest}/db/bdus.sqlite");
    \R::setup( "sqlite:./{$path2dest}/db/bdus.sqlite" );

    @touch("{$path2dest}/error.log");
    @touch("{$path2dest}/history.log");

  }
}
