PHPPDO is a database abstraction layer over the current PHP database functions,
which aim is to offer a migration path for new applications to the PDO classes
presented in PHP 5.1.

Why a migration path?
Currently, most hosting providers offering a shared hosting do not include
support for PDO in their PHP5 enabled web servers. That means most web
developers are stuck with the old database functions and can't migrate to PDO.
PHPPDO makes such a migration possible by providing a PDO-like API interface
over the old database functions. After using PHPPDO, a migration to PDO afterwards
is a matter of changing a single line of code (this can even be automatic,
when the script detects PDO).

Downloading PHPPDO
http://sourceforge.net/project/showfiles.php?group_id=216242

Using PHPPDO
Note: PHPPDO is designed in such a way that you can use it even if you have PDO
installed, but you do not have the specific PDO driver you need.
You can use PHPPDO to access, for example, Postgresql if you don't have
pdo_pgsql driver, but have pgsql extension loaded.

The following function checks whether you have PDO and the required PDO driver
enabled. If you have both, you'll get a PDO object, otherwise you'll get a PHPPDO
object.

function db_connect($dsn, $username = '', $password = '', $driver_options = array(), $path = './phppdo')
{
    $driver = strtolower(trim(substr($dsn, 0, strpos($dsn, ':'))));
    
    if($driver && class_exists('PDO') && extension_loaded('pdo_' . $driver))
    {
        $class = 'PDO';
    }
    else
    {
        require_once($path . '/phppdo.php');
        $class = 'PHPPDO';
    }
    
    return new $class($dsn, $username, $password, $driver_options);
}

Then you can use it to connect to the database like this:

try
{
    $db = db_connect('mysql:dbname=test', 'root', '', array(), '/path/to/phppdo/dir');
} catch(PDOException $e)
{
    die($e->getMessage());
}

Note: You can use the PHPPDO object in your application just like you use the
PDO object. For example, when you pass the object as a function argument you can
check the type of the object:

function foo(PDO $db) 
{
    ...
}

This is possible, because PHPPDO extends PDO when it's available, otherwise
a dummy PDO object is loaded. The same thing is valid for the statement object:

function bar(PDOStatement $stmt) 
{
    ...
}

For more info
Check the driver-specific topics at http://devuni.com/forums/viewforum.php?f=24

Currently supported drivers
1. MySQL driver (both mysql and mysqli extensions)
2. SQLite driver (only SQLite 2.x)
3. Postgresql driver
4. DBLib driver (both mssql and sybase_ct extensions)

PHPPDO caveats
1. You should not extend PHPPDO or the statement object, because that will break the compatibility.
2. Cursors are not supported.


For PHPPDO questions, help, comments, discussion, etc visit
http://devuni.com/forums/

Copyright 2007, 2008, 2009 Nikolay Ananiev.
author Nikolay Ananiev <admin at devuni dot com>