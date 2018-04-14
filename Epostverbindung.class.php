<?php

/**
 * Class Epostverbindung
 * Baut eine SFTP-Verbindung zur Epostbox auf und führt Dateioperationen damit aus.
 * See https://www.sitepoint.com/using-ssh-and-sftp-with-php/ for further help.
 */
class Epostverbindung
{
    private $host = ""; // Webserver (statische Adresse).
    private $port = ""; // SFTP-Port (meistens 22).
    private $username = ""; // Username der Epostbox (meistens epost).
    private $password = ""; // Passwort der Epostbox.

    private $connection, $sftp, $root, $mysqli;

    public function __construct($mysqli=null) {
        $this->mysqli = $mysqli;

        // Verbindung aufbauen.
        $this->connection = @ssh2_connect($this->host, $this->port);
        if(!$this->connection){
            throw new Exception("Keine Verbindung zu $this->host auf Port $this->port. moeglich");
        }

        // Einloggen.
        if (! @ssh2_auth_password($this->connection, $this->username, $this->password)){
            throw new Exception("Einloggen nicht moeglich.");
        }

        // SFTP Verbindung aufbauen.
        $this->sftp = @ssh2_sftp($this->connection);
        if(!$this->sftp) {
            throw new Exception("SFTP nicht moeglich.");
        }

        // Root-Verzeichnis festlegen.
        $this->root = 'ssh2.sftp://'.$this->sftp.'/epost/';
    }

    /**
     * Holt den aktuellen Verzeichnisbaum als Array.
     * @param string $remotePfad
     * @return array|bool
     */
    public function holeVerzeichnisbaum($remotePfad = ""){
        return scandir($this->root . $remotePfad);
    }

    /**
     * Loescht Datei auf der Epostbox.
     * @param string $remotePfad
     * @return bool
     */
    public function deleteDatei($remotePfad){
        return unlink($this->root . $remotePfad);
    }

    /**
     * Lädt Datei in den angegebenen Pfad auf der Epostbox.
     * @param $localPfad
     * @param $remotePfad
     * @throws Exception
     */
    public function dateiNachEpost($localPfad, $remotePfad){
        $stream = @fopen($this->root . $remotePfad, 'w');
        if(!$stream){
            throw new Exception("Kann Datei $this->root$remotePfad auf Epostbox nicht oeffnen.");
        }

        $content = @file_get_contents($localPfad);
        if($content === false){
            throw new Exception("Kann $localPfad nicht oeffnen.");
        }

        if(@fwrite($stream, $content) === false){
            throw new Exception("Datei $localPfad kann nicht gesendet werden.");
        }

        @fclose($stream);
    }

    /**
     * Holt Datei von dem angegebenen Pfad auf der Epostbox.
     * @param $localPfad
     * @param $remotePfad
     * @throws Exception
     */
    public function dateiVonEpost($localPfad, $remotePfad){
        $stream = @fopen($this->root . $remotePfad, 'r');
        if (!$stream){
            throw new Exception("Kann Datei $remotePfad auf Epostbox nicht oeffnen.");
        }

        // Warum nicht file_get_contents?
        $content = fread($stream, filesize($this->root . $remotePfad));
        if($content === false){
            throw new Exception("Kann $localPfad nicht oeffnen.");
        }

        file_put_contents ($localPfad, $content);

        @fclose($stream);
    }

    /**
     *
     * @param string $datei
     * @return string
     */
    private function holeDateiEindung($datei){
        $datei = str_replace("_", ".", $datei);
        $temp = explode(".", $datei);
        return $temp[count($temp)-1];
    }

    /**
     * @param $datei
     * @return string
     */
    private function holeDateiArt($datei){
        $datei = str_replace("../", "", $datei);
        $datei = str_replace("pdf/", "", $datei);
        $temp = explode("_", $datei);
        return $temp[0];
    }

    /**
     * Zählt alle Dateien im Verzeichnis.
     * @param string $remotePfad
     * @param string $art
     * @return int
     */
    public function holeAnzahlDateien($remotePfad = "", $art = ""){
        $verzeichnis = $this->holeVerzeichnisbaum($remotePfad);
        $count = 0;

        // Alle Dateien durchgehen.
        foreach ($verzeichnis as $file){
              $dateiEndung = $this->holeDateiEindung($file);
              $dateiArt = $this->holeDateiArt($file);
              $count += 1;
            }
        }

        return $count;
    }
    
    /**
     * Zählt alle Unterordner im Verzeichnis.
     * @param string $remotePfad
     * @return int
     */
    public function holeAnzahlVerzeichnisse($remotePfad = ""){
        $anzahl = count($this->holeVerzeichnisbaum($remotePfad)) - $this->holeAnzahlDateien($remotePfad);
        return $anzahl;
    }
}
