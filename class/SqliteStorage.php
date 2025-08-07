<?php
class SqliteStorage
{
    private $db;

    public function __construct($config)
    {
        $this->db = new PDO('sqlite:' . $config['path']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }

    private function initDatabase()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS main (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT "text",
            user_name TEXT NOT NULL,
            quote_source TEXT,
            is_hidden INTEGER NOT NULL DEFAULT 0,
            add_time INTEGER NOT NULL
        )');
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getQuotes()
    {
        $stmt = $this->db->query('SELECT * FROM main ORDER BY add_time DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getQuoteById($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM main WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLastUpdateTime()
    {
        $stmt = $this->db->query('SELECT MAX(add_time) as last_update FROM main');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['last_update'] ?? time();
    }
}
