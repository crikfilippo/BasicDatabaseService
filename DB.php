<?php

namespace DB;

use stdClass;
use PDO;
use PDOStatement;


/**
 * @method self query(string $sql, array $params = []) Prepare SQL query
 * @method static self query(string $sql, array $params = []) Prepare SQL query
 * @method ?int execute() Execute prepared query, returns ID or affected rows
 * @method array|stdClass|null get(int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null) Fetch all results
 * @method array|stdClass|null first(int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null) Fetch first result
 * @method PaginatedDataset paginate(int $perPage = 15, ?string $pageGETParamName = 'page', ?int $page = null, int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null) Paginate results
 */
class DB {

	private static $allowedPDOModes = [PDO::FETCH_ASSOC, PDO::FETCH_OBJ];
	private static ?string $host = null;
	private static ?int $port = null;
	private static ?string $dbname = null;
	private static ?string $user = null;
	private static ?string $pass = null;
	private static ?string $dsn = null;
	public static bool $isReady = false;

	private ?PDO $pdo = null;
	public ?string $sql = null;
	public ?PDOStatement $stmt = null;
	public array $queryParams = [];
	public mixed $queryResult = null;
	public int $affectedRows = 0;
	public ?int $lastInsertId = null;

	public function __construct(){
		$this->reset();
	}

	public static function checkReady(){
        if( ! self::$isReady){ throw new \Exception('DB not initialized, please use ::setParams()'); }
    }

    public static function setParams(
		#[\SensitiveParameter] ?string $host = null, 
		#[\SensitiveParameter] ?int $port = null, 
		#[\SensitiveParameter] ?string $dbname = null, 
		#[\SensitiveParameter] ?string $user = null, 
		#[\SensitiveParameter] ?string $pass = null,
	 ) {

	 	try{

			self::$isReady = false;
			
			//obtain params
			do{

				//from arguments
				if( ! is_null($host) || ! is_null($port) || ! is_null($dbname) || ! is_null($user) || ! is_null($pass)){ 
					break; 
				}

				//from static
				if( ! is_null(self::$host) || ! is_null(self::$port) || ! is_null(self::$dbname) || ! is_null(self::$user) || ! is_null(self::$pass) ){
					$host = self::$host;
					$port = self::$port;
					$dbname = self::$dbname;
					$user = self::$user;
					$pass = self::$pass;
					break;
				}
				
				//from env
				$host = getenv('DBHOST');
				$port = getenv('DBPORT');
				$dbname = getenv('DBNAME');
				$user = getenv('DBUSER');
				$pass = getenv('DBPSW');
		
			}while(false);

			//invalid params
			if( empty($host) || empty($port) || empty($dbname) || empty($user) || empty($pass)){ 

				throw new \Exception('DB: invalid parameters'); 

			}

			//save as static
			self::$host = $host;
			self::$port = $port;
			self::$dbname = $dbname;
			self::$user = $user;
			self::$pass = $pass;
			self::$dsn =  "mysql:host={$host};port={$port};dbname={$dbname}";

			self::$isReady = true;

			//test connection
			self::query('SELECT 1')->first();

			

		}

		catch(\Throwable $t){

			self::$host = null;
			self::$port = null;
			self::$dbname = null;
			self::$user = null;
			self::$pass = null;
			self::$dsn =  null;

			self::$isReady = false;

			//hide sensitive data from trace
			die('DB: cannot establish connection.'); 

		}

    }

	public function reset(){

		try{

			$this->sql = null;
			$this->stmt = null;
			$this->queryParams = [];
			$this->queryResult = null;
			$this->affectedRows = 0;
			$this->lastInsertId = null;

			self::checkReady();

			if( ! is_null($this->pdo) ){ return; }
			$this->pdo = new PDO(self::$dsn, self::$user, self::$pass);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		}catch(\Throwable $t){
			die(
				'DB: error while resetting the class. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}

	}

	public function __call(string $name, array $arguments){

		if($name == 'query'){
			return self::_query(
				$this,
				$arguments[0],
				$arguments[1] ?? []
			); 
		}

	}

	public static function __callStatic(string $name, array $arguments){

		if($name == 'query'){
			return self::_query(
				new self(),
				$arguments[0],
				$arguments[1] ?? []
			); 
		}

	}

    public static function _query(
		DB $db,
		string $sql, 
		array $params = [] //associative
	 ): self {
		self::checkReady();
		try{
			$db->reset();
			$db->sql = $sql;
			$db->queryParams = $params;
			$db->stmt = $db->pdo->prepare($sql);
			return $db;
		}catch(\Throwable $t){
			die(
				'DB: error while preparing the query. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}
	}

	public function execute(): ?int {
		self::checkReady();
		try{
			if( is_null($this->stmt) ){ throw new \Exception('no query prepared'); }
			$this->queryResult = $this->stmt->execute($this->queryParams);
			$this->affectedRows = $this->stmt->rowCount();
			$this->lastInsertId = $this->pdo->lastInsertId();
			if(str_contains(strtoupper($this->sql),'INSERT')){ $res = $this->lastInsertId; }
			else{ $res = $this->affectedRows; }
			return is_int($res) ? $res : null;
		}catch(\Throwable $t){
			die(
				'DB: error while executing the query. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}
    }

	public function get(int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null, bool $isSingleResult = false) : array|stdClass|null {
		self::checkReady();
		try{
			if( is_null($this->stmt) ){ throw new \Exception('no query prepared'); }
			if( ! in_array($mode,self::$allowedPDOModes) ){ throw new \Exception('invalid PDO fetch mode'); }
			foreach ($this->queryParams as $key => $value) { $this->stmt->bindValue(':'.$key, $value); }
			$this->stmt->execute(); 
			$this->queryResult = $this->stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
			if(empty($this->queryResult) || $this->queryResult === false){ $this->queryResult = []; return []; }
			return $this->mapResult($mapColumnToAttribute,$mode,$isSingleResult);
		}catch(\Throwable $t){
			die(
				'DB: error while fetching data. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}
	}

	public function first(int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null) : array|stdClass|null {
		self::checkReady();
		try{
			if( is_null($this->stmt) ){ throw new \Exception('no query prepared'); }
			if( ! in_array($mode,self::$allowedPDOModes) ){ throw new \Exception('invalid PDO fetch mode'); }
			$this->query("SELECT * FROM (".$this->sql.") a LIMIT 1", $this->queryParams);	
			return $this->get($mode,$mapColumnToAttribute, isSingleResult : true) ?? null;
		}catch(\Throwable $t){
			die(
				'DB: error while preparing the query. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}
	}

	public function paginate(int $perPage = 15, ?string $pageGETParamName = 'page', ?int $page = null, int $mode = PDO::FETCH_OBJ, ?array $mapColumnToAttribute = null) : PaginatedDataset{
		self::checkReady();	
		try{
			if( is_null($this->stmt) ){ throw new \Exception('no query prepared'); }
			if( ! in_array($mode,self::$allowedPDOModes) ){ throw new \Exception('invalid PDO fetch mode'); }

			$dataset = new PaginatedDataset();
			$originalSql = $this->sql;
			$originalParams = $this->queryParams;
			
			//usa query originale per contare totale record
			$db = new self();
			$db->query("SELECT count(*) C FROM (".$originalSql.") a", $originalParams);
			$db->first(PDO::FETCH_ASSOC);
			$dataset->total = $db->queryResult['C'] ?? 0; 

			//ottieni pagina corrente da parametro o da GET, fallback a 1
			if($page !== null){ $dataset->currentPage = max(1, $page); }
			else{
				$dataset->currentPage = array_key_exists($pageGETParamName,$_GET) && !empty($_GET[$pageGETParamName]) && ( ((int) $_GET[$pageGETParamName]) > 0 ) ? $_GET[$pageGETParamName] : 1; 
				$dataset->perPage = $perPage;
				$dataset->lastPage = max(1, ((int) ceil($dataset->total / $perPage)) );
			}

			//usa query originale con limit e offset per ottenere record della pagina corrente
			$db = new self();
			$limit = $dataset->perPage;
			$offset = ($dataset->currentPage - 1) * $dataset->perPage;
			$db->query("SELECT * FROM (".$originalSql.") a LIMIT {$limit} OFFSET {$offset}", $originalParams);

			$items = $db->get($mode, $mapColumnToAttribute);
			$dataset->items = is_array($items) ? $items : [$items];
			return $dataset;	

		}catch(\Throwable $t){
			die(
				'DB: error while paginating the results. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}	
	}

	private function mapResult(?array $mapColumnToAttribute = null, int $mode = PDO::FETCH_OBJ, bool $isSingleResult = false) : array|stdClass|null {
		try{ 
			if($mode == PDO::FETCH_ASSOC && is_null($mapColumnToAttribute)){ //no conversion needed
				$this->queryResult = $isSingleResult ? $this->queryResult[0] : $this->queryResult;
				return $this->queryResult; 
			} 
			if( ! is_null($mapColumnToAttribute) && empty($mapColumnToAttribute)){ return $isSingleResult ? null : []; }
			$columns = array_keys($this->queryResult[0]);
			$finalResults = [];
			foreach ($this->queryResult as $srcResult) {
				$finalResult = $mode == PDO::FETCH_OBJ ? new stdClass() : [];
				foreach ($columns as $column) {
					//no map, keep original column name as attribute name
					if( is_null($mapColumnToAttribute) ){ 
						if($mode == PDO::FETCH_OBJ){ $finalResult->$column = $srcResult[$column]; }
						else{ $finalResult[$column] = $srcResult[$column]; }
						continue; 
					}
					//map original column name to attribute name
					$attr =  array_key_exists($column,$mapColumnToAttribute) ? $mapColumnToAttribute[$column] : $column;
					if($mode == PDO::FETCH_OBJ){ $finalResult->{$attr} = $srcResult[$column]; }
					else{ $finalResult[$attr] = $srcResult[$column]; }
				}
				$finalResults[] = $finalResult;
			}
			$this->queryResult = $isSingleResult ? $finalResults[0] : $finalResults;
			return $this->queryResult;
		}catch(\Throwable $t){
			die(
				'DB: error while mapping the column. '
				.PHP_EOL.$t->getMessage()
				.PHP_EOL.$t->getLine()
			); 
		}	
	}

}

class PaginatedDataset{

	public int $total;    
	public int $currentPage; 
	public int $lastPage;
	public int $perPage;
	public array $items; 

	public function __construct(
		int $total = 0,
		int $currentPage = 1,
		int $lastPage = 1,
		int $perPage = 15,
		array $items = []
	){

		$this->total = $total;
		$this->currentPage = $currentPage;
		$this->lastPage = $lastPage;
		$this->perPage = $perPage;
		$this->items = $items;

	}

}

}
