<?php
// {{{ Database class
define("FORCETYPE", false); //force the extension that will be used (set to false in almost all circumstances except debugging)
class Database
{
    protected $db; //reference to the DB object
    protected $type; //the extension for PHP that handles SQLite
    protected $data;
    protected $lastResult;

    public function __construct($data)
    {
        $this->data = $data;
        try
        {
            if(file_exists($this->data["path"]) && !is_writable($this->data["path"])) //make sure the actual database file is writable
            {
                echo "<div class='confirm' style='margin:20px;'>";
                echo "The database, '".$this->data["path"]."', is not writable. The application is unusable until you make it writable.";
                echo "<form action='".PAGE."' method='post'/>";
                echo "<input type='submit' value='Log Out' name='logout' class='btn'/>";
                echo "</form>";
                echo "</div><br/>";
                exit();
            }

            if(!file_exists($this->data["path"]) && !is_writable(dirname($this->data["path"]))) //make sure the containing directory is writable if the database does not exist
            {
                echo "<div class='confirm' style='margin:20px;'>";
                echo "The database, '".$this->data["path"]."', does not exist and cannot be created because the containing directory, '".dirname($this->data["path"])."', is not writable. The application is unusable until you make it writable.";
                echo "<form action='".PAGE."' method='post'/>";
                echo "<input type='submit' value='Log Out' name='logout' class='btn'/>";
                echo "</form>";
                echo "</div><br/>";
                exit();
            }

            $ver = $this->getVersion();

            switch(true)
            {
            case (FORCETYPE=="PDO" || ((FORCETYPE==false || $ver!=-1) && class_exists("PDO") && ($ver==-1 || $ver==3))):
                $this->db = new PDO("sqlite:".$this->data['path']);
                if($this->db!=NULL)
                {
                    $this->type = "PDO";
                    break;
                }
            case (FORCETYPE=="SQLite3" || ((FORCETYPE==false || $ver!=-1) && class_exists("SQLite3") && ($ver==-1 || $ver==3))):
                $this->db = new SQLite3($this->data['path']);
                if($this->db!=NULL)
                {
                    $this->type = "SQLite3";
                    break;
                }
            case (FORCETYPE=="SQLiteDatabase" || ((FORCETYPE==false || $ver!=-1) && class_exists("SQLiteDatabase") && ($ver==-1 || $ver==2))):
                $this->db = new SQLiteDatabase($this->data['path']);
                if($this->db!=NULL)
                {
                    $this->type = "SQLiteDatabase";
                    break;
                }
            default:
                $this->showError();
                exit();
            }
        }
        catch(Exception $e)
        {
            $this->showError();
            exit();
        }
    }

    public function showError()
    {
        $classPDO = class_exists("PDO");
        $classSQLite3 = class_exists("SQLite3");
        $classSQLiteDatabase = class_exists("SQLiteDatabase");
        if($classPDO)
            $strPDO = "installed";
        else
            $strPDO = "not installed";
        if($classSQLite3)
            $strSQLite3 = "installed";
        else
            $strSQLite3 = "not installed";
        if($classSQLiteDatabase)
            $strSQLiteDatabase = "installed";
        else
            $strSQLiteDatabase = "not installed";
        echo "<div class='confirm' style='margin:20px;'>";
        echo "There was a problem setting up your database, ".$this->getPath().". An attempt will be made to find out what's going on so you can fix the problem more easily.<br/><br/>";
        echo "<i>Checking supported SQLite PHP extensions...<br/><br/>";
        echo "<b>PDO</b>: ".$strPDO."<br/>";
        echo "<b>SQLite3</b>: ".$strSQLite3."<br/>";
        echo "<b>SQLiteDatabase</b>: ".$strSQLiteDatabase."<br/><br/>...done.</i><br/><br/>";
        if(!$classPDO && !$classSQLite3 && !$classSQLiteDatabase)
            echo "It appears that none of the supported SQLite library extensions are available in your installation of PHP. You may not use ".PROJECT." until you install at least one of them.";
        else
        {
            if(!$classPDO && !$classSQLite3 && $this->getVersion()==3)
                echo "It appears that your database is of SQLite version 3 but your installation of PHP does not contain the necessary extensions to handle this version. To fix the problem, either delete the database and allow ".PROJECT." to create it automatically or recreate it manually as SQLite version 2.";
            else if(!$classSQLiteDatabase && $this->getVersion()==2)
                echo "It appears that your database is of SQLite version 2 but your installation of PHP does not contain the necessary extensions to handle this version. To fix the problem, either delete the database and allow ".PROJECT." to create it automatically or recreate it manually as SQLite version 3.";
            else
                echo "The problem cannot be diagnosed properly. Please email me at daneiracleous@gmail.com with your database as an attachment and the contents of this error message. It may be that your database is simply not a valid SQLite database, but this is not certain.";
        }
        echo "</div><br/>";
    }

    public function __destruct()
    {
        if($this->db)
            $this->close();
    }

    //get the exact PHP extension being used for SQLite
    public function getType()
    {
        return $this->type;
    }

    //get the name of the database
    public function getName()
    {
        return $this->data["name"];
    }

    //get the filename of the database
    public function getPath()
    {
        return $this->data["path"];
    }

    //get the version of the database
    public function getVersion()
    {
        if(file_exists($this->data['path'])) //make sure file exists before getting its contents
        {
            $content = strtolower(file_get_contents($this->data['path'], NULL, NULL, 0, 40)); //get the first 40 characters of the database file
            $p = strpos($content, "** this file contains an sqlite 2"); //this text is at the beginning of every SQLite2 database
            if($p!==false) //the text is found - this is version 2
                return 2;
            else
                return 3;
        }
        else //return -1 to indicate that it does not exist and needs to be created
        {
            return -1;
        }
    }

    //get the size of the database
    public function getSize()
    {
        return round(filesize($this->data["path"])*0.0009765625, 1)." Kb";
    }

    //get the last modified time of database
    public function getDate()
    {
        return date("g:ia \o\\n F j, Y", filemtime($this->data["path"]));
    }

    //get number of affected rows from last query
    public function getAffectedRows()
    {
        if($this->type=="PDO")
            return $this->lastResult->rowCount();
        else if($this->type=="SQLite3")
            return $this->db->changes();
        else if($this->type=="SQLiteDatabase")
            return $this->db->changes();
    }

    public function close()
    {
        if($this->type=="PDO")
            $this->db = NULL;
        else if($this->type=="SQLite3")
            $this->db->close();
        else if($this->type=="SQLiteDatabase")
            $this->db = NULL;
    }

    public function beginTransaction()
    {
        $this->query("BEGIN");
    }

    public function commitTransaction()
    {
        $this->query("COMMIT");
    }

    public function rollbackTransaction()
    {
        $this->query("ROLLBACK");
    }

    //generic query wrapper
    public function query($query, $ignoreAlterCase=false)
    {
        if(strtolower(substr(ltrim($query),0,5))=='alter' && $ignoreAlterCase==false) //this query is an ALTER query - call the necessary function
        {
            $queryparts = preg_split("/[\s]+/", $query, 4, PREG_SPLIT_NO_EMPTY);
            $tablename = $queryparts[2];
            $alterdefs = $queryparts[3];
            //echo $query;
            $result = $this->alterTable($tablename, $alterdefs);
        }
        else //this query is normal - proceed as normal
            $result = $this->db->query($query);
        if(!$result)
            return NULL;
        $this->lastResult = $result;
        return $result;
    }

    //wrapper for an INSERT and returns the ID of the inserted row
    public function insert($query)
    {
        $result = $this->query($query);
        if($this->type=="PDO")
            return $this->db->lastInsertId();
        else if($this->type=="SQLite3")
            return $this->db->lastInsertRowID();
        else if($this->type=="SQLiteDatabase")
            return $this->db->lastInsertRowid();
    }

    //returns an array for SELECT
    public function select($query, $mode="both")
    {
        $result = $this->query($query);
        if(!$result) //make sure the result is valid
            return NULL;
        if($this->type=="PDO")
        {
            if($mode=="assoc")
                $mode = PDO::FETCH_ASSOC;
            else if($mode=="num")
                $mode = PDO::FETCH_NUM;
            else
                $mode = PDO::FETCH_BOTH;
            return $result->fetch($mode);
        }
        else if($this->type=="SQLite3")
        {
            if($mode=="assoc")
                $mode = SQLITE3_ASSOC;
            else if($mode=="num")
                $mode = SQLITE3_NUM;
            else
                $mode = SQLITE3_BOTH;
            return $result->fetchArray($mode);
        }
        else if($this->type=="SQLiteDatabase")
        {
            if($mode=="assoc")
                $mode = SQLITE_ASSOC;
            else if($mode=="num")
                $mode = SQLITE_NUM;
            else
                $mode = SQLITE_BOTH;
            return $result->fetch($mode);
        }
    }

    //returns an array of arrays after doing a SELECT
    public function selectArray($query, $mode="both")
    {
        $result = $this->query($query);
        if(!$result) //make sure the result is valid
            return NULL;
        if($this->type=="PDO")
        {
            if($mode=="assoc")
                $mode = PDO::FETCH_ASSOC;
            else if($mode=="num")
                $mode = PDO::FETCH_NUM;
            else
                $mode = PDO::FETCH_BOTH;
            return $result->fetchAll($mode);
        }
        else if($this->type=="SQLite3")
        {
            if($mode=="assoc")
                $mode = SQLITE3_ASSOC;
            else if($mode=="num")
                $mode = SQLITE3_NUM;
            else
                $mode = SQLITE3_BOTH;
            $arr = array();
            $i = 0;
            while($res = $result->fetchArray($mode))
            {
                $arr[$i] = $res;
                $i++;
            }
            return $arr;
        }
        else if($this->type=="SQLiteDatabase")
        {
            if($mode=="assoc")
                $mode = SQLITE_ASSOC;
            else if($mode=="num")
                $mode = SQLITE_NUM;
            else
                $mode = SQLITE_BOTH;
            return $result->fetchAll($mode);
        }
    }

    //multiple query execution
    public function multiQuery($query)
    {
        if($this->type=="PDO")
        {
            $this->db->exec($query);
        }
        else if($this->type=="SQLite3")
        {
            $this->db->exec($query);
        }
        else
        {
            $this->db->queryExec($query);
        }
    }
    
    public function exec($query)
    {
        if ($this->type=="PDO")
        {
            $affected = $this->db->exec($query);
            if ($affected === false) {
                return $this->db->errorInfo();
            }
            return $affected;
        }
        else
        {
            return false;
        }
    }

    //get number of rows in table
    public function numRows($table)
    {
        $result = $this->select("SELECT Count(*) FROM ".$table);
        return $result[0];
    }

    //correctly escape a string to be injected into an SQL query
    public function quote($value)
    {
        if($this->type=="PDO")
        {
            return $this->db->quote($value);
        }
        else if($this->type=="SQLite3")
        {
            return $this->db->escapeString($value);
        }
        else
        {
            return "'".$value."'";
        }
    }

    //correctly format a string value from a table before showing it
    public function formatString($value)
    {
        return htmlspecialchars(stripslashes($value));
    }
}
// }}}

// {{{ HTML generators

function generate_select($name, $items, $selected, $k, $v, $id=null)
{
    if (!$id)
        $id = "select_$name";
    
    $html = "<select id='$id' name='$name' class='select_$k'>";
    foreach ($items as $item) {
        $key = $item[$k];
        $curr = $item[$v];
        if ($selected === $curr) $is_selected = "selected";
        else $is_selected = "";
        $curr = htmlentities($curr);
        $html .= "<option value='$key' ".$is_selected.">";
        $html .= $curr;
        $html .= "</option>";
    }
    $html .= "</select>";
    return $html;
}

function generate_transactions_table($transactions, $categories)
{
    // 1st pass - transform
    $by_account = array();
    foreach ($transactions as $t) {
        if (!array_key_exists($t['account_name'], $by_account)) {
            $by_account[$t['account_name']] = array();
        }
        $by_account[$t['account_name']][] = $t;
    }

    // 2nd pass - render
    $html = '';
    foreach ($by_account as $k => $v) {
        $html .= "<tr'><td colspan='4'><font color='#789CC7'><strong>$k</strong></font></td></tr>";
        foreach ($v as $tr) {
            $html .= "<tr>";
            $html .= "<td>".$tr['transaction_date']."</td>";
            $html .= "<td class='yui-dt-col-description'>"
                ."<a href=''>".htmlentities($tr['description'])."</a>"
                ."<div style='display:none'>".$tr['id']."</div>"
                ."</td>";
            $curr = $tr['category'];
            $html .= "<td>" . generate_select('category', $categories, $curr, 'category', 'category', $tr['id']) . "</td>";
            if ($tr['amount'] < 0)
                $klass = 'row-debit';
            else
                $klass = 'row-credit';
            $html .= "<td class='$klass'>£".number_format($tr['amount'], 2)."</td>";
            $html .= "</tr>";
        }
    }
    return $html;
}

// }}}

// {{{ Helpers

function extract_date_modifiers($date_modifier, $ignore=null)
{
    if (!$date_modifier) return null;
    if ($ignore && $date_modifier === $ignore) return null;
    
    $from = array("'now'");
    $to = array("'now'");
    $date_modifier_arr = explode(':', $date_modifier);
    
    if (sizeof($date_modifier_arr) > 0) {
        if ($date_modifier_arr[0] === 'literal') {
            // date literal format
            $from = array("'".$date_modifier_arr[1]."'");
            $to = array("'".$date_modifier_arr[2]."'");
        } else {
            // modifier format
            $from_arr = explode(',', $date_modifier_arr[0]);
            foreach ($from_arr as $m) {
                $from[] = "'$m'";
            }
            
            // second element is optional
            if (sizeof($date_modifier_arr) > 1) {
                $to_arr = explode(',', $date_modifier_arr[1]);
                foreach ($to_arr as $m) {
                    $to[] = "'$m'";
                }
            }
        }
    }
    
    return array('from' => $from, 'to' => $to);
}

// }}}

// {{{ Data accessors

function get_groups($db)
{
    return $db->selectArray("
        select distinct group_name
        from groups
        order by group_name
    ");
}

function get_accounts($db)
{
    return $db->selectArray("
        select distinct account_name
        from transactions
        order by account_name
    ");
}

function get_categories($db)
{
    return $db->selectArray("
        select distinct category
        from transactions
        order by category
    ");
}

function get_single_transaction($db, $id)
{
    $safe_id = $db->quote($id);
    return $db->select("
        select *
        from transactions
        where id = $safe_id
    ");
}

function get_transactions($db, $date_modifier)
{
    return $db->selectArray("
        select *
        from transactions
        where transaction_date >= date(".implode(',', $date_modifier['from']).")
        order by transaction_date desc
    ");
}

function make_transactions_query($db, $query_type, $category, $account, $date_modifier, $expenses_only, $start=0, $results=0, $needs_wants=NULL)
{
    $query = 'select ';
    if ($query_type === 'transactions')
        $query .= '* ';
    else
        $query .= 'count(*) ';
    
    $query .= 'from transactions ';
        
    $where = 'where';
    if ($category && $category !== 'All Categories') {
        $safe_category = $db->quote($category.'%');
        $query .= "$where category like $safe_category ";
        $where = 'and';
    }
    
    if ($account && $account !== 'All Accounts') {
        $safe_account = $db->quote($account);
        $query .= "$where account_name = $safe_account ";
        $where = 'and';
    }
    
    if ($date_modifier) {
        $query .= "
            $where transaction_date >= date(".implode(',', $date_modifier['from']).")
            and transaction_date <= date(".implode(',', $date_modifier['to']).")
        ";
        $where = 'and';
    }
    
    if ($expenses_only) {
        $query .= "
            $where amount < 0
        ";
        $where = 'and';
    }
    
    if ($needs_wants) {
        $safe_needs_wants = $db->quote($needs_wants);
        $query .= "
            $where needs_wants_savings = $needs_wants
        ";
        $where = 'and';        
    }
    
    if ($query_type === 'transactions') {
        $query .= "
            order by transaction_date desc
            limit $results 
            offset $start
        ";
    }
    return $query;
}

function get_transactions_page($db, $start, $results, $category, $account, $date_modifier, $expenses_only=FALSE, $needs_wants=NULL)
{
    $query = make_transactions_query($db, 'transactions', $category, $account, $date_modifier, $expenses_only, $start, $results);    
    return $db->selectArray($query);
}

function count_transactions($db, $category, $account, $date_modifier, $expenses_only=FALSE)
{
    $query = make_transactions_query($db, 'count', $category, $account, $date_modifier, $expenses_only);    
    $rs = $db->select($query);
    return $rs[0];
}

function get_top_n_expense_categories($db, $n, $category, $account, $date_modifier)
{
    $from_str = implode(',', $date_modifier['from']);
    $to_str = implode(',', $date_modifier['to']);
    $query = "
        select category
              ,abs(sum(amount)) as amount_sum
              ,date($from_str) as start_date
              ,date($to_str) as end_date
        from transactions
        where transaction_date >= date($from_str)
        and transaction_date <= date($to_str)
        and category != 'Transfers'
    ";
    
    if ($category && $category !== 'All Categories') {
        $safe_category = $db->quote($category.'%');
        $query .= "and category like $safe_category ";
    }

    if ($account && $account !== 'All Accounts') {
        $safe_account = $db->quote($account);
        $query .= "and account_name = $safe_account ";
    }
    
    $query .= "
        group by category
        having amount < 0
        order by amount_sum desc
        limit $n
    ";

    return $db->selectArray($query);
}

function get_spending_by_category($db, $category, $date_modifier, $expenses_only)
{
    $safe_category = $db->quote($category.'%');
    $query = "
        select abs(sum(amount)) as amount_sum,
               transaction_date,
               strftime('%m/%Y', transaction_date) as month_date
        FROM transactions
        where category != 'Transfers'
    ";
    
    if ($expenses_only)
        $query .= 'and amount < 0 ';
            
    if ($category && $category !== 'All Categories') {
        $safe_category = $db->quote($category.'%');
        $query .= "and category like $safe_category ";
    }

    if ($date_modifier) {
        $query .= "
            and transaction_date >= date(".implode(',', $date_modifier['from']).")
            and transaction_date <= date(".implode(',', $date_modifier['to']).")
        ";
    }
    
    $query .= "
        group by month_date
        order by transaction_date
    ";

    return $db->selectArray($query);
}

function get_spending_by_balanced_money_formula($db, $date_modifier)
{
    $query = "
        select abs(sum(amount)) as amount_sum,
               needs_wants_savings
        from transactions
        where needs_wants_savings != 'Exempt'
    ";

    if ($date_modifier) {
        $query .= "
            and transaction_date >= date(".implode(',', $date_modifier['from']).")
            and transaction_date <= date(".implode(',', $date_modifier['to']).")
        ";
    }
    
    $query .= "
        group by needs_wants_savings
    ";

    return $db->selectArray($query);
}

function search($db, $criteria) 
{
    // todo: <-1000 style queries fail, probably due to encoding
    $query = "
        select *
        from transactions
        ";
    $first_char = $criteria[0];
    if (in_array($first_char, array('<', '=', '>'))) {
        $amount = floatval(substr($criteria, 1));
        $query .= "
            where amount $first_char $amount
            ";
    } else {
        $safe_criteria = $db->quote('%'.$criteria.'%');
        $query .= "
            where description like $safe_criteria
            or category like $safe_criteria
            ";
    }
    $query .= "
        order by transaction_date desc
        limit 200
        ";
    
    return $db->selectArray($query);
}

// }}}

// {{{ Data modifiers

/*
The problem, as it turns out, is that the PDO SQLite driver requires that if you are going to do a write operation (INSERT,UPDATE,DELETE,DROP, etc), then the folder the database resides in must have write permissions, as well as the actual database file.
http://stackoverflow.com/questions/3319112/pdo-sqlite-read-only-database
*/
function update_category($db, $id, $category)
{
    $safe_category = $db->quote($category);
    return $db->exec("update transactions set category = $safe_category where id = $id");
}

function delete_transaction($db, $id)
{
    return $db->exec("delete from transactions where id = $id");
}

// }}}

// {{{ API

function action_get_single_transaction($db)
{
    $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    return json_encode(get_single_transaction($db, $id));
}

function action_get_transactions_table($db)
{
    $modifiers = extract_date_modifiers($_GET['modifiers']); // todo
        
    $transactions = get_transactions($db, $modifiers);
    $categories = get_categories($db);
    return generate_transactions_table($transactions, $categories);
}

function action_get_transactions_page_json($db)
{
    $results = filter_input(INPUT_GET, 'results', FILTER_SANITIZE_NUMBER_INT);
    $start = filter_input(INPUT_GET, 'startIndex', FILTER_SANITIZE_NUMBER_INT);
    $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
    $account = filter_input(INPUT_GET, 'account', FILTER_SANITIZE_STRING);
    $modifiers = extract_date_modifiers($_GET['date_modifier'], 'All Transactions');
    $expenses_only = filter_input(INPUT_GET, 'expenses_only', FILTER_SANITIZE_STRING);
        
    $transactions = get_transactions_page($db, $start, $results, $category, $account, $modifiers, $expenses_only);
    $count = count_transactions($db, $category, $account, $modifiers, $expenses_only);
    
    return json_encode(
        array(
            'records' => $transactions, 
            'recordsReturned' => sizeof($transactions),
            'totalRecords' => intval($count),
            'sort' => 'transaction_date',
            'dir' => 'desc',
            'startIndex' => intval($start),
            'pageSize' => intval($results),
            )
    );
}

function action_update_category($db)
{
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    return json_encode(update_category($db, $id, $category));
}

function action_search($db)
{
    $criteria = filter_input(INPUT_POST, 'text', FILTER_SANITIZE_STRING);
    $transactions = search($db, $criteria);
    return json_encode(
        array(
            'records' => $transactions, 
            'recordsReturned' => sizeof($transactions),
            'totalRecords' => sizeof($transactions),
            'sort' => 'transaction_date',
            'dir' => 'desc',
            'startIndex' => 0,
            'pageSize' => 50,
            )
    );
}

function action_get_expense_categories($db)
{
    $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
    $account = filter_input(INPUT_GET, 'account', FILTER_SANITIZE_STRING);
    $modifiers = extract_date_modifiers($_GET['modifiers']); // todo
    return json_encode(get_top_n_expense_categories($db, 8, $category, $account, $modifiers));
}

function action_get_spending_by_category($db)
{
    $modifiers = extract_date_modifiers($_GET['modifiers'], 'All Transactions'); // todo
    $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_STRING);
    $expenses_only = filter_input(INPUT_GET, 'expenses_only', FILTER_SANITIZE_STRING);
    if ($expenses_only === "false") $expenses_only = false;
    return json_encode(get_spending_by_category($db, $category, $modifiers, $expenses_only));
}

function action_get_spending_by_balanced_money_formula($db)
{
    $modifiers = extract_date_modifiers($_GET['modifiers'], 'All Transactions'); // todo
    return json_encode(get_spending_by_balanced_money_formula($db, $modifiers));
}

function action_delete_transaction($db)
{
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    if (!$id) 
        return json_encode(array('ERROR' => 'Invalid id'));
    
    return json_encode(delete_transaction($db, $id));
}

// }}}

$database = array();
$database['path'] = './dosh.sqlite';
$database['name'] = 'dosh';

$db = new Database($database);

$current_page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_ENCODED);
if (!$current_page) {
    // check for an api call
    $fn = filter_var($_REQUEST['action'], FILTER_SANITIZE_STRING);
    if (!$fn) {
        // didn't get a page or an action - redirect
        header('Location: dosh.php?page=dashboard');
    } else {
        $fn = 'action_'.$fn;
        echo call_user_func($fn, $db);
    }
    exit();
}

?>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
        <title>DOSH</title>
        <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/combo?2.6.0/build/paginator/assets/skins/sam/paginator.css&2.6.0/build/datatable/assets/skins/sam/datatable.css&2.6.0/build/calendar/assets/skins/sam/calendar.css">
        <!-- {{{ Stylesheet -->
        <style type="text/css">
        /* overall styles for entire page */
        body {
            margin: 0px;
            padding: 0px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
        
        .yui-skin-sam .yui-dt-col-description {
            color:#2F6A97; 
        }
        
        .yui-skin-sam .yui-dt-col-description a {
            text-decoration: none;
            color:#2F6A97; 
        }
        
        .yui-skin-sam tr.yui-dt-even { background-color:#FFF; }  
        .yui-skin-sam tr.yui-dt-odd { background-color:#FFF; } 
        .yui-skin-sam .yui-dt td { 
            border:none; 
            border-bottom:1px solid #CBCBCB;
        }
        .yui-skin-sam .yui-dt th { border:none; }
        .yui-skin-sam .yui-dt table { border:none; }
        
        .row-debit { color:red; }

        </style>
        
        <!-- }}} -->
    </head>

    <body>
        <div>
            <?php if ($current_page !== 'view_trans') { ?>
                <a href="dosh.php?page=dashboard">Dashboard</a> |
                <a href="dosh.php?page=transactions">Transactions</a> |
                <a href="dosh.php?page=expense_analysis">Expense Analysis</a> |
                <a href="dosh.php?page=expense_by_category">Expenses By Category</a> |
                <a href="dosh.php?page=needs_wants_savings">Balanced Money Formula</a> |
                <?php if ($current_page === 'transactions') { ?>
                    <input type='text' id='search_text' /><input type='button' id='search' value='Search' />
                <?php } ?>
            <?php } ?>
        </div>
        <div>
            <?php
                switch ($current_page) {
                    // {{{ Dashboard
                    case 'dashboard':
            ?>
                        <div>
                            <div style="float:left">
                                <div id="dashboard_chart_div"></div>
                            </div>

                            <div style="float:left; padding-top: 25px;"><strong>Recent Transactions</strong>
                                <div>
                                    Select group:
                                    <?php
                                        $groups = get_groups($db);
                                        echo generate_select('groups', $groups, '', 'group_name', 'group_name');
                                    ?>
                                    from past:
                                    <select id='select_date_modifier' name='date_modifier'>
                                        <option value="-7 days">1 week</option>
                                        <option value="-14 days">2 weeks</option>
                                    </select>
                                    <input type="submit" id="dashboard_filter_transactions" value="Show" />
                                </div>
                                <table id='dashboard_transactions' class='yui-skin-sam'>
                                </table>
                            </div>
                        </div>
            <?php
                        break;
                    // }}}
                    // {{{ Transactions
                    case 'transactions':
            ?>
                        <div style="float:left; padding-top: 25px;"><strong>Transactions</strong>
                            <form action="dosh.php" method="GET">
                                <div>
                                    Select group:
                                    <?php
                                        $accounts = get_accounts($db);
                                        array_unshift($accounts, array('account_name' => 'All Accounts'));
                                        echo generate_select('account', $accounts, '', 'account_name', 'account_name');
                                    ?>
                                    Select category:
                                    <?php
                                        $categories = get_categories($db);
                                        array_unshift($categories, array('category' => 'All Categories'));
                                        echo generate_select('category', $categories, '', 'category', 'category');
                                    ?>
                                    Select Time Period:
                                    <select id='select_date_modifier' name='date_modifier'>
                                        <option value="All Transactions">All Transactions</option>
                                        <option value="-7 days">1 week</option>
                                        <option value="-14 days">2 weeks</option>
                                        <option value="start of month">This month</option>
                                        <option value="start of month,-1 month:start of month,-1 day">Last month</option>
                                        <option value="start of year">This year</option>
                                        <option value="start of year,-1 year:start of year,-1 day">Last year</option>
                                    </select>
                                    <input type="hidden" id="page" name="page" value="transactions" />
                                    <input type="submit" id="transactions_filter_transactions" value="Show" />
                                </div>
                            </form>
                            
                            <div class="yui-skin-sam">
                                <div id='transactions_transactions'>
                                </div>
                            </div>
                        </div>
            <?php
                        break;
                    // }}}
                    case 'expense_analysis':
            ?>
                        <div>
                            <div>
                                <form action="dosh.php" method="GET">
                                    <div>
                                        Select group:
                                        <?php
                                            $accounts = get_accounts($db);
                                            array_unshift($accounts, array('account_name' => 'All Accounts'));
                                            echo generate_select('account', $accounts, '', 'account_name', 'account_name');
                                        ?>
                                        Select category:
                                        <?php
                                            $categories = get_categories($db);
                                            array_unshift($categories, array('category' => 'All Categories'));
                                            echo generate_select('category', $categories, '', 'category', 'category');
                                        ?>
                                        <input type="hidden" id="page" name="page" value="expense_analysis" />
                                        <input type="submit" id="expense_analysis_filter_transactions" value="Show" />
                                    </div>
                                </form>
                            </div>
                             <div style="float:left;">
                               <div>
                                    <div id="expense_analysis_chart_div"></div>
                                </div>
                                <div>
                                    <input type="button" class="time_period_button" id="-1 month" value="1M" />
                                    <input type="button" class="time_period_button" id="-3 month" value="3M" />
                                    <input type="button" class="time_period_button" id="-6 month" value="6M" />
                                    <input type="button" class="time_period_button" id="-12 month" value="12M" />
                                    <input type="button" class="time_period_button" id="start of month" value="This month" />
                                    <input type="button" class="time_period_button" id="start of month,-1 month:start of month,-1 day" value="Last month" />
                                    <input type="button" class="time_period_button" id="start of year" value="This year" />
                                    <input type="button" class="time_period_button" id="start of year,-1 year:start of year,-1 day" value="Last year" />
                                    <input type="button" class="time_period_button" id="custom" value="Custom" />
                                    <div style="display:none" id='select_custom_dates'>
                                        <div style="float:left;">
                                            From
                                            <div class="yui-skin-sam">
                                                <div id='custom_cal1'></div>
                                            </div>
                                        </div>
                                        <div>
                                            To
                                            <div class="yui-skin-sam">
                                                <div id='custom_cal2'></div>
                                            </div>
                                        </div>
                                        <input type="submit" id="submit_custom_date" value="Submit" />
                                    </div>
                                </div>
                            </div>
                            <div style="padding-top: 25px;" class="yui-skin-sam">
                                <div id="expense_analysis_categories">
                                </div>
                            </div>
                            <div style="clear:both"></div>
                            <div style="padding-top: 10px;" >
                                <strong>Transactions</strong>
                                <div class="yui-skin-sam">
                                    <div id="expense_analysis_transactions">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
            <?php
                        break;
                    case 'expense_by_category':
                        $expenses_only = filter_input(INPUT_GET, 'expenses_only', FILTER_SANITIZE_STRING);
            ?>
                        <div>
                            <form action="dosh.php" method="GET">
                                <div>
                                    Select group:
                                    <?php
                                        $accounts = get_accounts($db);
                                        array_unshift($accounts, array('account_name' => 'All Accounts'));
                                        echo generate_select('account', $accounts, '', 'account_name', 'account_name');
                                    ?>
                                    Select category:
                                    <?php
                                        $categories = get_categories($db);
                                        array_unshift($categories, array('category' => 'All Categories'));
                                        echo generate_select('category', $categories, '', 'category', 'category');
                                    ?>
                                    Select Time Period:
                                    <select id='select_date_modifier' name='date_modifier'>
                                        <option value="All Transactions">All Transactions</option>
                                        <option value="-7 days">1 week</option>
                                        <option value="-14 days">2 weeks</option>
                                        <option value="start of month">This month</option>
                                        <option value="start of month,-1 month:start of month">Last month</option>
                                        <option value="start of year">This year</option>
                                        <option value="start of year,-1 year:start of year">Last year</option>
                                    </select>
                                    <input type="checkbox" id="chk_expenses_only" name="expenses_only" 
                                        <?php if ($expenses_only) echo 'checked="yes"'?> 
                                    >
                                        Show expenses only
                                    </input>
                                    <input type="hidden" id="page" name="page" value="expense_by_category" />
                                    <input type="submit" id="expense_by_category_filter_transactions" value="Show" />
                                </div>
                            </form>
                            <div>
                                <div id="expense_by_category_chart_div"></div>
                            </div>
                            <div class="yui-skin-sam">
                                <div id='expense_by_category_transactions'>
                                </div>
                            </div>
                        </div>
            <?php
                        break;
                    case 'needs_wants_savings':
            ?>
                        <div>
                            <div id="needs_wants_savings_chart_div"></div>
                            <div>
                                <input type="button" class="time_period_button" id="-1 month" value="1M" />
                                <input type="button" class="time_period_button" id="-3 month" value="3M" />
                                <input type="button" class="time_period_button" id="-6 month" value="6M" />
                                <input type="button" class="time_period_button" id="-12 month" value="12M" />
                                <input type="button" class="time_period_button" id="start of month" value="This month" />
                                <input type="button" class="time_period_button" id="start of month,-1 month:start of month,-1 day" value="Last month" />
                                <input type="button" class="time_period_button" id="start of year" value="This year" />
                                <input type="button" class="time_period_button" id="start of year,-1 year:start of year,-1 day" value="Last year" />
                            </div>
                        </div>     
            <?php
                }
            ?>
        </div>
        <div style="clear:both"></div>
    </body>

    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load("jquery", "1.6.1");
        google.load("visualization", "1", {packages:["corechart"]});
    </script>
    <script type="text/javascript" src="http://yui.yahooapis.com/combo?2.6.0/build/yahoo-dom-event/yahoo-dom-event.js&2.6.0/build/animation/animation-min.js&2.6.0/build/connection/connection-min.js&2.6.0/build/element/element-beta-min.js&2.6.0/build/paginator/paginator-min.js&2.6.0/build/datasource/datasource-min.js&2.6.0/build/datatable/datatable-min.js&2.6.0/build/json/json-min.js&2.6.0/build/calendar/calendar-min.js"></script>
    
    <!-- {{{ General javascript -->
    <script type="text/javascript">
        // pie chart colors
        var colors = [
                    '#3366CC','#DC3912','#FF9900','#109618',
                    '#990099','#0099C6','#DD4477','#66AA00',
                ];

        function getDateAsIsoString(date) {
            return date.getFullYear() 
                 + '-'
                 + ('0' + (date.getMonth()+1)).slice(-2)
                 + '-'
                 + ('0' + date.getDate()).slice(-2);
        }
        
        function getParameterByName(name) {
            name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
            var regexS = "[\\?&]"+name+"=([^&#]*)";
            var regex = new RegExp( regexS );
            var results = regex.exec(window.location.href);
            if (results == null)
                return "";
            else
                return decodeURIComponent(results[1].replace(/\+/g, " "));
        }
        
        function createSpendingByCategoryChart(data, category, div_id) {
            var datatbl = new google.visualization.DataTable();
            datatbl.addColumn('string', 'Date');
            datatbl.addColumn('number', category);
            datatbl.addRows(data.length);
            
            for (var i = 0; i < data.length; i++) {
                datatbl.setValue(i, 0, data[i].month_date);
                datatbl.setValue(i, 1, parseFloat(data[i].amount_sum));
            }
            
           var chart = new google.visualization.ColumnChart(document.getElementById(div_id));
            chart.draw(datatbl, {
                width: 650, 
                height: 400, 
                legend: 'none',
                title: 'Spending by category', 
                colors:['green'],
                hAxis: {title: 'Date'}
            });
        }

        function getSpendingByCategory(modifier, category, expenses_only, next) {
            $.get('dosh.php',
                    { 
                        action: 'get_spending_by_category', 
                        modifiers: modifier, 
                        category: category,
                        expenses_only: expenses_only,
                    },
                    next,
                    'json'
            )
            .error(function() { alert('error'); })
            ;
        }
        
        // {{{ Expenses stuff
        
        function getExpensesByCategory(category, account, date_modifier, next) {
            $.get('dosh.php',
                { 
                    action: 'get_expense_categories', 
                    category: category,
                    account: account,
                    modifiers: date_modifier,
                },
                next,
                'json'
            )
            .error(function() { alert('error'); })
            ;
        }
        
        function createExpenseCategoryChart(data, div_id) {
            // augment data with colors
            $.each(data, function(i,v) {
                v.color = colors[i];
            });

            var datatbl = new google.visualization.DataTable();
            datatbl.addColumn('string', 'Category');
            datatbl.addColumn('number', 'Quids');
            datatbl.addRows(data.length);
            
            for (var i = 0; i < data.length; i++) {
                datatbl.setValue(i, 0, data[i].category);
                datatbl.setValue(i, 1, parseFloat(data[i].amount_sum));
            }

            var chart = new google.visualization.PieChart(document.getElementById(div_id));
            chart.draw(datatbl, {
                width: 500,
                height: 450,
                legend: 'bottom',
                colors: colors,
                title: 'Expense Analysis (' + data[0].start_date + ' to ' + data[0].end_date + ')',
                titleTextStyle: {
                    fontName: 'Arial',
                    fontSize: 14,
                },
            });
        }
        
        function createExpenseCategoryTable(data, div_id) {
            var total = 0.0;
            $.each(data, function(i, v) {
                total += parseFloat(v.amount_sum);
            });
            
            var categoryData = $.map(data, function(d, i) {
                return {
                    color: d.color,
                    category: d.category,
                    percent: (parseFloat(d.amount_sum) / total) * 100,
                    amount: parseFloat(d.amount_sum)
                };
            });
            
            function percentFormatter(el,r,c,d) {
                var percent = r.getData("percent");
                el.innerHTML = percent.toFixed(1) + '%';
            }
            
            function colorFormatter(el,r,c,d) {
                var color = r.getData("color");
                el.style.background = color;
            }
            
            var datasrc = new YAHOO.util.LocalDataSource(categoryData);
            datasrc.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
            datasrc.responseSchema = {
                fields: ["color","category","percent","amount"],
            };
        
            var cols = [
                    {key:"color", label:"Color", formatter:colorFormatter},
                    {key:"category", label:"Category"},
                    {key:"percent", label:"Percent", formatter:percentFormatter},
                    {key:"amount", label:"Amount", formatter:'currency'},
                ];
            
            var configs = {
                currencyOptions:{prefix: "£", decimalPlaces:2, decimalSeparator:".", thousandsSeparator:","},
            };
            var tbl = new YAHOO.widget.DataTable(div_id, cols, datasrc, configs);
        }
        
        function getNeedsWantsSavings(date_modifier, next) {
            $.get('dosh.php',
                { 
                    action: 'get_spending_by_balanced_money_formula', 
                    modifiers: date_modifier,
                },
                next,
                'json'
            )
            .error(function() { alert('error'); })
            ;
        }
        
        function createNeedsWantsSavingsChart(data, div_id) {
            var datatbl = new google.visualization.DataTable();
            datatbl.addColumn('string', 'Category');
            datatbl.addColumn('number', 'Quids');
            datatbl.addRows(data.length);
            
            for (var i = 0; i < data.length; i++) {
                datatbl.setValue(i, 0, data[i].needs_wants_savings);
                datatbl.setValue(i, 1, parseFloat(data[i].amount_sum));
            }

            var chart = new google.visualization.PieChart(document.getElementById(div_id));
            chart.draw(datatbl, {
                width: 500,
                height: 450,
                legend: 'bottom',
                colors: colors,
                title: 'Balanced Money Formula',
                titleTextStyle: {
                    fontName: 'Arial',
                    fontSize: 14,
                },
            });
        }
        
        // }}}
        
        function updateCategory(id, category) {
            var newCategory = false;
            if (category == 'New...') {
                category = prompt("Enter new category:");
                newCategory = true;
            }
            
            $.ajax({
                  type: "POST",
                  url: "dosh.php",
                  data: {action: 'update_category', id:id, category:category},
                  dataType: "json",
                  success: function(data, status) {
                      if (newCategory) location.reload(true);
                  },
                  error: function(xhr, statusText, e) {
                      alert("Error: " + xhr.responseText);
                  }
            });
        }
    
        // {{{ Transactions stuff
        
        function createTransactionsTableAjaxDataSource(category, account, date_modifier, expenses_only) {
            var datasrcQuery = "dosh.php?action=get_transactions_page_json&";
            
            if (category && category != 'All Categories') 
                datasrcQuery += "category=" + category + "&";
                
            if (account && account != 'All Accounts') 
                datasrcQuery += "account=" + account + "&";

            if (date_modifier && date_modifier != 'All Transactions')
                datasrcQuery += "date_modifier=" + date_modifier + "&";

            if (expenses_only)
                datasrcQuery += "expenses_only=true&";

            var datasrc = new YAHOO.util.XHRDataSource(datasrcQuery);
            datasrc.responseType = YAHOO.util.DataSource.TYPE_JSON;
            datasrc.responseSchema = {
                resultsList: "records",
                fields: [
                            {key:"id"},
                            {key:"transaction_date"},
                            {key:"description"},
                            {key:"category"},
                            {key:"needs_wants_savings"},
                            {key:"amount"},
                            {key:"account_name"},
                        ],
                metaFields: {
                    totalRecords: "totalRecords"
                }
            };
            
            return datasrc;
        }
        
        function createTransactionsTableAjaxConfigs() {
            return {
                initialRequest: "sort=transaction_date&dir=desc&startIndex=0&results=50",
                dynamicData: true,
                paginator: new YAHOO.widget.Paginator({rowsPerPage: 50}),
                currencyOptions:{prefix: "£", decimalPlaces:2, decimalSeparator:".", thousandsSeparator:","},
            };
        }
        
        function createTransactionsTableLocalConfigs() {
            return {
                paginator: new YAHOO.widget.Paginator({rowsPerPage: 50}),
                currencyOptions:{prefix: "£", decimalPlaces:2, decimalSeparator:".", thousandsSeparator:","},
            };
        }
        
        function createTransactionsTableLocalDataSource(data) {
            var datasrc = new YAHOO.util.LocalDataSource(data);
            datasrc.responseType = YAHOO.util.DataSource.TYPE_JSON;
            datasrc.responseSchema = {
                resultsList: "records",
                fields: [
                            {key:"id"},
                            {key:"transaction_date"},
                            {key:"description"},
                            {key:"category"},
                            {key:"needs_wants_savings"},
                            {key:"amount"},
                            {key:"account_name"},
                        ],
                metaFields: {
                    totalRecords: "totalRecords"
                }
            };
            
            return datasrc;
        }
        
        function createTransactionsTableColumns() {
            // grab options from already-populated list at top of page
            var elements = $('#select_category option');
            var categories = $.map(elements, function(e, i) { return $(e).val();});
            categories.unshift('New...');
            
            function currencyFormatterOverride(el,r,c,d) {
                if (d < 0)
                    YAHOO.util.Dom.addClass(el, 'row-debit');
                
                el.innerHTML = YAHOO.util.Number.format(d, this.get("currencyOptions"));
            }
            
            function descriptionFormatterOverride(el,r,c,d) {
                el.innerHTML = '<a href="">' + d + '</a><div style="display:none">' + r.getData('id') + '</div>';
            };
            
            function deleteFormatterOverride(el,r,c,d) {
                el.innerHTML = '<a href="" class="delete-transaction">x</a><div style="display:none">' + r.getData('id') + '</div>';
            };
            
            return [
                {key:"id", label:"ID", hidden:true},
                {key:"transaction_date", label:"Date"},
                {key:"description", label:"Description", formatter:descriptionFormatterOverride},
                {key:"category", label:"Category", formatter:'dropdown', dropdownOptions:categories},
                {key:"needs_wants_savings", label:"Balanced Money"},
                {key:"amount", label:"Amount", formatter:currencyFormatterOverride},
                {key:"account_name", label:"Account"},
                {key:"delete", label:"Delete", formatter:deleteFormatterOverride},
            ];
        }
        
        function createTransactionsTable(datasrc, configs, cols, div) {
            // grab options from already-populated list at top of page
            var elements = $('#select_category option');
            var categories = $.map(elements, function(e, i) { return $(e).val();});
            categories.unshift('New...');
            
            var tbl = new YAHOO.widget.DataTable(div, cols, datasrc, configs);
            tbl.handleDataReturnPayload = function(req, resp, payload) {
                payload.totalRecords = resp.meta.totalRecords;
                return payload;
            };
            
            tbl.subscribe("dropdownChangeEvent", function(args) {
               var combo = args.target;
               var data = this.getRecord(combo).getData();
               updateCategory(data.id, combo.value);
            });
            
            tbl.subscribe("postRenderEvent", function() {
                $('div.yui-dt-liner > a').click(function(e) {
                    var id = $(e.target.nextSibling).text();
                    // ugh, do this with selectors
                    if (e.target.className == 'delete-transaction') {
                        deleteSingleTransaction(id);
                    } else {
                        alertSingleTransaction(id);
                    }
                    return false;
                });
            });
            
            return tbl;
        }
        
        function showDashboardTransactions(group, modifier) {
            $.get('dosh.php',
                    { action: 'get_transactions_table', modifiers: modifier }, // todo: group
                    function(data) {
                        $('#dashboard_transactions').html(data);
                        $('.select_category').change(function(e) {
                            var combo = e.target;
                            updateCategory(combo.id, combo.value);
                        });
                                        
                        // description click handler
                        if ($('.yui-dt-col-description > a').length) {
                            $('.yui-dt-col-description > a').click(function(e) {
                                var id = $(e.target.nextSibling).text();
                                alertSingleTransaction(id);
                                return false;
                            });
                        }
                    },
                    'html'
            )
            .error(function() { alert('error'); })
            ;
        }
        
        function deleteSingleTransaction(id) {
            $.ajax({
                  type: "POST",
                  url: "dosh.php",
                  data: {action: 'delete_transaction', id:id},
                  dataType: "json",
                  success: function(data, status) {
                      location.reload(true);
                  },
                  error: function(xhr, statusText, e) {
                      alert("Error: " + xhr.responseText);
                  }
            });
        }
        
        function alertSingleTransaction(id) {
            $.get('dosh.php',
                    { action: 'get_single_transaction', id: id },
                    function(data) {
                        alert(
                            "ID\t\t\t\t: " + data['id'] + "\n" + 
                            "Hash\t\t\t: " + data['fingerprint'].slice(0,20) + "...\n" + 
                            "Date\t\t\t: " + data['transaction_date'] + "\n" + 
                            "Account\t\t\t: " + data['account_name'] + "\n" + 
                            "Category\t\t\t: " + data['category'] + "\n" + 
                            "Amount\t\t\t: " + data['amount'] + "\n" + 
                            "Split\t\t\t: " + data['split'] + "\n" + 
                            "Description\t\t: " + data['description'] + "\n" + 
                            "Balanced Money\t: " + data['needs_wants_savings']
                        );
                    },
                    'json'
            )
            .error(function() { alert('error'); })
            ;
        }
        
        // }}}
        
        function showCalendar(id, div_id) {
            var cal = new YAHOO.widget.Calendar(id, div_id)
            cal.render();
            cal.show();
            return cal;
        }
        
        // doc ready
        $(document).ready(function () {
            var category = getParameterByName('category');
            var account = getParameterByName('account');
            var date_modifier = getParameterByName('date_modifier');
            
            if ($('#select_category').length) 
                $('#select_category').val(category);
            if ($('#select_account').length) 
                $('#select_account').val(account);
            if ($('#select_date_modifier').length) 
                $('#select_date_modifier').val(date_modifier);
                
            // scripting for dashboard page
            if ($('#dashboard_filter_transactions').length) {
                showDashboardTransactions('', '-7 days');
                getExpensesByCategory(
                    'All Categories',
                    'All Accounts',
                    'start of month,-3 month',
                    function (data) {
                        createExpenseCategoryChart(data, 'dashboard_chart_div');
                    }
                );
                
                $('#dashboard_filter_transactions').click(function() {
                    var group = $('#select_groups').val();
                    var modifier = $('#select_date_modifier').val();
                    showDashboardTransactions(group, modifier);
                });
            }
            
            // scripting for transactions page
            if ($('#transactions_transactions').length) {
                createTransactionsTable(
                    createTransactionsTableAjaxDataSource(category, account, date_modifier, false), 
                    createTransactionsTableAjaxConfigs(),
                    createTransactionsTableColumns(),
                    'transactions_transactions'
                );
            
                $('#search').click(function() {
                    var txt = $('#search_text').val();
                    $.ajax({
                        type: "POST",
                        url: "dosh.php",
                        data: {action: 'search', text:txt},
                        dataType: "json",
                        success: function(data, status) {
                            createTransactionsTable(
                                createTransactionsTableLocalDataSource(data), 
                                createTransactionsTableLocalConfigs(), 
                                createTransactionsTableColumns(),
                                'transactions_transactions'
                                );
                        },
                        error: function(xhr, statusText, e) {
                            alert("Error: " + xhr.responseText);
                        }
                    });           
                });
            }
            
            // scripting for expense analysis page
            if ($('#expense_analysis_filter_transactions').length) {
                function refresh_expense_analysis(category, account, modifier) {
                    getExpensesByCategory(
                        category,
                        account,
                        modifier,
                        function (data) {
                            createExpenseCategoryChart(data, 'expense_analysis_chart_div');
                            createExpenseCategoryTable(data, 'expense_analysis_categories');
                        }
                    );
                    createTransactionsTable(
                        createTransactionsTableAjaxDataSource(category, account, modifier, true), 
                        createTransactionsTableAjaxConfigs(),
                        createTransactionsTableColumns(),
                        'expense_analysis_transactions'
                    );
                }
                
                refresh_expense_analysis(category, account, 'start of month,-3 month');
                
                $('.time_period_button').click(function(e) {
                    if (e.target.id == 'custom') {
                        $('#select_custom_dates').show();
                        var cal1 = showCalendar('cal1', 'custom_cal1');
                        var cal2 = showCalendar('cal2', 'custom_cal2');
                        
                        $('#submit_custom_date').click(function(e) {
                            var d1 = cal1.getSelectedDates()[0] || new Date();
                            var d2 = cal2.getSelectedDates()[0] || new Date();
                            
                            var modifier = 'literal:'
                                + getDateAsIsoString(d1) + ':'
                                + getDateAsIsoString(d2);
                                
                            refresh_expense_analysis(category, account, modifier);
                        });
                        
                    } else {
                        $('#select_custom_dates').hide();
                        refresh_expense_analysis(category, account, e.target.id);
                    }
                });
                
            }
            
            // scripting for category spending page
            if ($('#expense_by_category_chart_div').length) {
                function refresh_expense_by_category(category, account, modifier) {
                    var expenses_only = $('#chk_expenses_only').is(':checked');
                    
                    getSpendingByCategory(modifier, category, expenses_only,
                        function (data) {
                            createSpendingByCategoryChart(data, category, 'expense_by_category_chart_div');
                        }
                    );

                    createTransactionsTable(
                        createTransactionsTableAjaxDataSource(category, account, modifier, expenses_only), 
                        createTransactionsTableAjaxConfigs(),
                        createTransactionsTableColumns(),
                        'expense_by_category_transactions'
                    );
                }
                
                refresh_expense_by_category(category, account, date_modifier);
            }

            // scripting for balanced money formula
            if ($('#needs_wants_savings_chart_div').length) {
                function refresh_needs_wants_savings(modifier) {
                    getNeedsWantsSavings(modifier, 
                        function(data) {
                            createNeedsWantsSavingsChart(data, 'needs_wants_savings_chart_div');
                        }
                    );
                }
                refresh_needs_wants_savings('start of month,-3 month');

                $('.time_period_button').click(function(e) {
                    refresh_needs_wants_savings(e.target.id);
                });
            }
        });
    </script>
    <!-- }}} -->
</html>

<?php
$db->close();
?>

<!-- vim: set foldmethod=marker: -->