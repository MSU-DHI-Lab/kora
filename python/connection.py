import MySQLdb
from MySQLdb import cursors
from env import env
from table import BaseFieldTypes

class Connection:
    """
    Adapter for the MySQLdb interface.
    """
    def __init__(self):
        """
        Connection constructor.
        Uses the environment variables defined in env.json.
        """
        connection_dict = {
            "host": env("DB_HOST"),
            "user": env("DB_USERNAME"),
            "passwd": env("DB_PASSWORD"),
            "db": env("DB_DATABASE"),
            ##
            ## We want the results returned as a dictionary, but not have potentially thousands dumped into memory.
            ## So we use the SSDictCursor which stores the results on the server (MySQL Server) and fetches as needed.
            ##
            "cursorclass": cursors.SSDictCursor
        }

        self._cnx = MySQLdb.connect(**connection_dict) ## Spawn a cursor from here.

    def __del__(self):
        """
        Close the connection to the database
        """
        self._cnx.close()

    def cursor(self):
        """
        Get a new cursor from the connection.
        :return MySQLdb.cursors.DictCursor:
        """
        return self._cnx.cursor()

class Cursor:
    """
    Adapter for the MysQLdb cursor.
    Cursors execute queries.

    *** Note that cursors should not ever be returned to the user outside this class.
        Global cursors and other unforeseen methods called on cursors are unpredictable.
    """
    def __init__(self, connection):
        """
        Cursor constructor.
        :param connection: MySQLdb connection
        """
        self._cnx = connection
        self._prefix = env("DB_PREFIX")

    def get_typed_fields(self, rids, table_name):
        """
        A generator that yields typed fields of a group of records (represented by their rids).

        :param rids: list of rids to query with
        :param table_name: table name (must be in table.py)
        :raise Exception: when an invalid table name is provided.
        :return dict: dictionary representing a typed field.
        """

        ## Initialize cursor from DB Connection.
        cursor = self._cnx.cursor()

        ## Double check to make sure the table_name is valid.
        if not table_name in BaseFieldTypes.__dict__.values():
            raise Exception("Invalid table name in get_typed_fields.")

        stmt = "SELECT * FROM " + self._prefix + table_name + " "

        stmt += "WHERE `rid` = %s"
        for _ in range(len(rids) - 1):
            stmt += " OR `rid` = %s"

        cursor.execute(stmt, rids)

        for row in cursor:
            yield row

        cursor.close()