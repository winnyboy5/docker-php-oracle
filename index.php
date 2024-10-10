<?php

class ChannelDataManager {
    private $conn;
    private $db_config;

    public function __construct($db_config) {
        $this->db_config = $db_config;
        $this->connectToDatabase();
    }

    private function connectToDatabase() {
        $this->conn = oci_connect($this->db_config['username'], $this->db_config['password'], $this->db_config['connection_string']);
        if (!$this->conn) {
            $e = oci_error();
            die("Connection failed: " . $e['message']);
        }
    }

    public function createTableIfNotExists() {
        $create_table_sql = "BEGIN
            EXECUTE IMMEDIATE 'CREATE TABLE TEST_TABLE (
                ID NUMBER,
                PROVIDER VARCHAR2(100),
                GENRE VARCHAR2(100),
                CHANNEL_NAME VARCHAR2(100),
                CHANNEL_EPG VARCHAR2(100),
                CONSTRAINT pk_test_table PRIMARY KEY (ID, CHANNEL_NAME)
            )';
        EXCEPTION
            WHEN OTHERS THEN
                IF SQLCODE != -955 THEN
                    RAISE;
                END IF;
        END;";

        $stmt = oci_parse($this->conn, $create_table_sql);
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            echo "Error creating table: " . $e['message'] . "<br>";
        }
    }

    public function fetchApiData($url) {
        $json_data = file_get_contents($url);
        $data = json_decode($json_data, true);
        if ($data === null) {
            die("Invalid JSON data from API");
        }
        return $data;
    }

    public function displayAndMergeData($data) {
        echo "<h1>API Data</h1>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Provider</th><th>Genre</th><th>Channel Name</th><th>Channel EPG</th></tr>";

        foreach ($data['Entities'] as $entity) {
            foreach ($entity['channels'] as $channel) {
                $row_data = [
                    'id' => $entity['id'],
                    'provider' => $entity['provider'],
                    'genre' => $entity['genre'],
                    'channel_name' => $channel['name'],
                    'channel_epg' => implode(", ", $channel['epg'])
                ];

                $this->displayRow($row_data);
                $this->mergeDataIntoDatabase($row_data);
            }
        }

        echo "</table>";
    }

    private function displayRow($data) {
        echo "<tr>";
        foreach ($data as $value) {
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }

    private function mergeDataIntoDatabase($data) {
        $sql = "MERGE INTO TEST_TABLE t
                USING (SELECT :id AS ID, :provider AS PROVIDER, :genre AS GENRE, 
                              :channel_name AS CHANNEL_NAME, :channel_epg AS CHANNEL_EPG 
                       FROM dual) s
                ON (t.ID = s.ID AND t.CHANNEL_NAME = s.CHANNEL_NAME)
                WHEN MATCHED THEN
                    UPDATE SET t.PROVIDER = s.PROVIDER, 
                               t.GENRE = s.GENRE, 
                               t.CHANNEL_EPG = s.CHANNEL_EPG
                WHEN NOT MATCHED THEN
                    INSERT (ID, PROVIDER, GENRE, CHANNEL_NAME, CHANNEL_EPG)
                    VALUES (s.ID, s.PROVIDER, s.GENRE, s.CHANNEL_NAME, s.CHANNEL_EPG)";

        $stmt = oci_parse($this->conn, $sql);
        foreach ($data as $key => $value) {
            oci_bind_by_name($stmt, ":$key", $data[$key]);
        }
        
        $result = oci_execute($stmt);
        if (!$result) {
            $e = oci_error($stmt);
            echo "Error merging data: " . $e['message'] . "<br>";
        }
    }

    public function displayDatabaseData() {
        echo "<h2>Data from Database</h2>";
        $select_sql = "SELECT * FROM TEST_TABLE ORDER BY ID, CHANNEL_NAME";
        $stmt = oci_parse($this->conn, $select_sql);
        oci_execute($stmt);

        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Provider</th><th>Genre</th><th>Channel Name</th><th>Channel EPG</th></tr>";

        while ($row = oci_fetch_assoc($stmt)) {
            $this->displayRow($row);
        }

        echo "</table>";
    }

    public function __destruct() {
        if ($this->conn) {
            oci_close($this->conn);
        }
    }
}

// Usage
$db_config = [
    'username' => 'system',
    'password' => 'oracle',
    'connection_string' => 'db:1521/XE'
];

$manager = new ChannelDataManager($db_config);
$manager->createTableIfNotExists();
$api_data = $manager->fetchApiData("http://web/api.php");
$manager->displayAndMergeData($api_data);
$manager->displayDatabaseData();

?>