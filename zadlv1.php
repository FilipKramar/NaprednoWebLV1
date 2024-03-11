<?php

interface iRadovi
{
    public function create($html_content);
    public function save($naziv_rada, $tekst_rada, $link_rada, $oib_tvrtke);
    public function read();
}

class DiplomskiRadovi implements iRadovi
{
    public $naziv_rada;
    public $tekst_rada;
    public $link_rada;
    public $oib_tvrtke;
    private $conn;

    public function __construct()
    {
        // Spajanje na bazu podataka
        $this->conn = new mysqli("localhost", "root", "");

        // Provjera konekcije
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // Stvaranje baze podataka ako ne postoji i odabir baze podataka
        $this->createDatabase();
        // Stvaranje tablice 'diplomski_radovi' ako ne postoji
        $this->createTable();
    }

    private function createDatabase()
    {
        // SQL upit za stvaranje baze podataka ako ne postoji
        $sql = "CREATE DATABASE IF NOT EXISTS radovi";

        // Izvršavanje SQL upita
        if ($this->conn->query($sql) === TRUE) {
        } else {
            die("Greška pri stvaranju baze podataka: " . $this->conn->error);
        }

        // Odabir baze podataka 'radovi'
        $this->conn->select_db("radovi");
    }

    private function createTable()
    {
        // SQL upit za stvaranje tablice 'diplomski_radovi' ako ne postoji
        $sql = "CREATE TABLE IF NOT EXISTS diplomski_radovi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            naziv_rada VARCHAR(255) NOT NULL,
            tekst_rada TEXT,
            link_rada VARCHAR(255),
            oib_tvrtke VARCHAR(20)
        )";

        // Izvršavanje SQL upita
        if ($this->conn->query($sql) === TRUE) {
        } else {
            die("Greška pri stvaranju tablice 'diplomski_radovi': " . $this->conn->error);
        }
    }

    public function create($html_content)
    {
        // Pretvori HTML sadržaj u DOM objekt
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress warnings
        $dom->loadHTML($html_content);
        libxml_clear_errors();

        // Koristi XPath za pronalazak elemenata
        $xpath = new DOMXPath($dom);

        // Pronalaženje svih članaka unutar article elemenata
        $articles = $xpath->query('//article');

        // Provjera da li su članci pronađeni
        if ($articles->length > 0) {
            foreach ($articles as $article) {
                // Dohvaćanje linka tvrtke i naslova rada
                $link = $xpath->query('.//a[@class="fusion-link-wrapper"]', $article)->item(0);
                $link_tvrtke = '';
                $naslov_rada = '';

                if ($link) {
                    foreach ($link->attributes as $attr) {
                        if ($attr->name === 'href') {
                            $link_tvrtke = $attr->value;
                        } elseif ($attr->name === 'aria-label') {
                            $naslov_rada = $attr->value;
                        }
                    }
                }
                // Dohvaćanje teksta rada
                $tekst_rada = $xpath->query('.//div[@class="fusion-post-content-container"]/p', $article)->item(0)->nodeValue;
                $imageSrc = $xpath->query('.//img/@src', $article)->item(0)->nodeValue;
                // Pronalaženje broja koji je prije ".png" u src atributu slike
                preg_match('/(\d+)(?=\.\w+$)/', $imageSrc, $matches);
                $oib = $matches[0];

                // Postavljanje podataka objekta
                $this->naziv_rada = $naslov_rada;
                $this->tekst_rada = $tekst_rada;
                $this->link_rada = $link_tvrtke;
                $this->oib_tvrtke = $oib;

                // Poziv metode save kako bi se podaci spremljeni u bazu podataka
                $this->save($this->naziv_rada, $this->tekst_rada, $this->link_rada, $this->oib_tvrtke);
            }
        } else {
            echo "Nije moguće pronaći članke na stranici.";
        }
    }

    public function save($naziv_rada, $tekst_rada, $link_rada, $oib_tvrtke)
    {
        // Priprema SQL upita za spremanje rada
        $stmt = $this->conn->prepare("INSERT INTO diplomski_radovi (naziv_rada, tekst_rada, link_rada, oib_tvrtke) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $naziv_rada, $tekst_rada, $link_rada, $oib_tvrtke);

        // Izvršavanje upita
        if ($stmt->execute() === TRUE) {
        } else {
            echo "Error: " . $stmt->error . "<br>";
        }

        // Zatvaranje statementa
        $stmt->close();
    }

    public function read()
    {
        // Priprema SQL upita za dohvat svih radova
        $sql = "SELECT * FROM diplomski_radovi";
        $result = $this->conn->query($sql);

        if ($result->num_rows > 0) {
            // Ispis dobivenih radova
            while ($row = $result->fetch_assoc()) {
                echo "ID: " . $row["id"] . "<br>";
                echo "Naziv rada: " . $row["naziv_rada"] . "<br>";
                echo "Tekst rada: " . $row["tekst_rada"] . "<br>";
                echo "Link rada: " . $row["link_rada"] . "<br>";
                echo "OIB tvrtke: " . $row["oib_tvrtke"] . "<br><br>";
            }
        } else {
            echo "Nema rezultata";
        }
    }

    public function closeConnection()
    {
        // Zatvaranje konekcije
        $this->conn->close();
    }
}


// Inicijalizacija klase i poziv metode create
$redni_broj = 2;
$url = "https://stup.ferit.hr/index.php/zavrsni-radovi/page/$redni_broj";
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
$html_content = curl_exec($curl);

// Provjera grešaka
if ($html_content === false) {
    echo 'Greška prilikom izvršavanja cURL zahtjeva: ' . curl_error($curl);
} else {
    // Stvaranje objekta klase DiplomskiRadovi i poziv metode create
    $radovi = new DiplomskiRadovi();
    $radovi->create($html_content);
}

// Zatvaranje cURL sesije
curl_close($curl);
// Testiranje metode read
echo "Testiranje metode read:<br>";
$radovi->read();
