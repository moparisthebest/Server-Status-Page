/*
MoparScape.org server status page
Copyright (C) 2011  Travis Burtrum (moparisthebest)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
import java.io.DataInputStream;
import java.io.DataOutputStream;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.GregorianCalendar;
import java.util.Date;
import java.util.HashMap;

public class ServerChecker {

// remove dups : SELECT * FROM servers s1, servers s2 WHERE s1.id != s2.id AND s1.ipaddress = s2.ipaddress
/*
select bad_rows.*
from servers as bad_rows
   inner join (
SELECT s1.* FROM servers s1, servers s2 WHERE s1.id != s2.id AND s1.ipaddress = s2.ipaddress
   ) as good_rows on good_rows.id = bad_rows.id;
*/

    private static final String userName = "user";
    private static final String userPass = "pass";
    private static final String databaseUrl = "jdbc:mysql://localhost:3306/serverstat";

    private static final String sqlOr = " OR id=";

	private HashMap<String, String> iphostname = new HashMap<String, String>(1000);
    private Connection conn;

    public ServerChecker() {
	System.out.println(new Date()+" - STARTING...");
        try {
            connect();
 //           timeFromDate();
            updateServers();
            validateNewServers();
//            timestamp();
            disconnect();
            System.out.println(new Date()+" - FINISHED SUCCESSFULLY!!");
        } catch (Exception e) {
		System.out.println(new Date()+" - ERROR!!");
            e.printStackTrace(System.out);
        }
    }
/*
    private void timestamp() throws Exception {
        DataOutputStream dos = new DataOutputStream(new FileOutputStream("/opt/lampp/htdocs/moparscape.org/timestamp"));
        dos.writeLong(System.currentTimeMillis()/1000);
        dos.close();
    }
*/
    private void connect() throws Exception {
        Class.forName("com.mysql.jdbc.Driver").newInstance();
        conn = DriverManager.getConnection(databaseUrl, userName, userPass);
    }

    private void disconnect() throws Exception {
        conn.close();
    }

    private void safeExecuteUpdate(Statement stmt, String update){
	try{
	    stmt.executeUpdate(update);
	}catch(Exception e){
	    System.out.println("safeExecuteUpdate error!");
	    System.out.println("update: "+update);
	    System.out.println("Error: "+e.getMessage());
	    e.printStackTrace(System.out);
	}
    }

    private void updateServers() throws Exception {
        Statement stmt = conn.createStatement();
        ResultSet result = stmt.executeQuery("SELECT `id`, `ip`, `port` from `servers` where sponsored = '0'");

        StringBuilder online = new StringBuilder("UPDATE servers SET online=1, totalcount=totalcount+1, oncount=oncount+1, `uptime` = (oncount / totalcount * 100) WHERE id=");
        StringBuilder offline = new StringBuilder("UPDATE servers SET online=0, totalcount=totalcount+1, `uptime` = (oncount / totalcount * 100) WHERE id=");

        int onLength = online.length();
        int offLength = offline.length();

        while (result.next()) {
//            System.out.println("Name:\t" + result.getString("id"));
//            System.out.println("IP:\t" + result.getString("IP"));
//            System.out.println("Port:\t" + result.getString("Port"));
            if (validServer(result.getString("ip"), result.getInt("port"))) {
//                System.out.println("ONLINE");
                online.append(result.getString("id")).append(sqlOr);
            } else {
//                System.out.println("OFFLINE");
                offline.append(result.getString("id")).append(sqlOr);
            }
        }

        //System.out.println(online.substring(0, online.length()-sqlOr.length()));
        //System.out.println(offline.substring(0, offline.length()-sqlOr.length()));
        if(online.length() > onLength)
            safeExecuteUpdate(stmt, online.substring(0, online.length()-sqlOr.length()));
        if(offline.length() > offLength)
            safeExecuteUpdate(stmt, offline.substring(0, offline.length()-sqlOr.length()));
        safeExecuteUpdate(stmt, "DELETE FROM `servers` WHERE `uptime` < '40'");
    }

    private void validateNewServers() throws Exception {
        Statement stmt = conn.createStatement();
        ResultSet result = stmt.executeQuery("SELECT `id`, `ip`, `port` from `toadd` WHERE `verified` = '1'");

        Statement stmt2 = conn.createStatement();

        while (result.next()) {
//            System.out.println("Name:\t" + result.getString("id"));
//            System.out.println("IP:\t" + result.getString("IP"));
//            System.out.println("Port:\t" + result.getString("Port"));
            if (validServer(result.getString("ip"), result.getInt("port"))) {
 //               System.out.println("ONLINE");
                safeExecuteUpdate(stmt2, "INSERT INTO `servers` (`uid`, `uname`, `name`, `ip`, `port`, `version`, `time`, `info`, `ipaddress`, `rs_name`, `rs_pass`) SELECT `uid`, `uname`, `name`, `ip`, `port`, `version`, `time`, `info`, `ipaddress`, `rs_name`, `rs_pass` FROM `toadd` WHERE `id` = "+result.getString("id"));
		safeExecuteUpdate(stmt2, "DELETE FROM `toadd` WHERE `id` = "+result.getString("id"));
            }
        }
		// delete entries that are past a certain date old
		// System.currentTimeMillis()/1000 is unix timestamp
		// 86400 is 24 hours in seconds
		long oldSeconds = (System.currentTimeMillis()/1000) - 86400;
		safeExecuteUpdate(stmt2, "DELETE FROM `toadd` WHERE `time` < "+oldSeconds);
    }
/*
    private void timeFromDate() throws Exception {
        Statement stmt = conn.createStatement();
        ResultSet result = stmt.executeQuery("SELECT `id`, `date` from `servers`");

        Statement stmt2 = conn.createStatement();

        while (result.next()) {
            String[] date = result.getString("date").split("-");
            safeExecuteUpdate(stmt2, "UPDATE servers SET time="+new GregorianCalendar(Integer.parseInt("20"+date[2]),Integer.parseInt(date[0])-1,Integer.parseInt(date[1])).getTimeInMillis()/1000 +" WHERE id="+result.getString("id"));
        }
    }
*/
    private void deleteServers(String s1, String s2) throws Exception {
        Statement stmt = conn.createStatement();
        safeExecuteUpdate(stmt, "DELETE FROM `servers` WHERE `ip` = '"+s1+"' OR `ip` = '"+s2+"'");
    }

    private boolean validServer(String ip, int port) throws Exception {
        Socket s;
        try {
            s = new Socket();
            s.setSoTimeout(2000);
		InetSocketAddress addy = new InetSocketAddress(ip, port);
		if(addy.isUnresolved())
			return false;
//		System.out.println("addy: "+addy.getAddress().getHostAddress());
		String resolvedIP = addy.getAddress().getHostAddress();
		if(iphostname.containsKey(resolvedIP)){
//			System.out.println("deleteServers("+ip+", "+iphostname.get(resolvedIP)+")");
			// if you delete the server in the database, anyone can delete any server by posting a duplicate
			// instead simply don't allow this one in.
			//deleteServers(ip, iphostname.get(resolvedIP));
			return false;
		}
//		System.out.println("iphostname.put("+resolvedIP+", "+ip+")");
		iphostname.put(resolvedIP, ip);
            s.connect(addy, 2000);
        } catch (Exception e) {
            //           e.printStackTrace();
            return false;
        }
	try {
            s.close();
        } catch (Exception e) {}
	return true;
/*      try {
            DataOutputStream out = new DataOutputStream(s.getOutputStream());
            DataInputStream in = new DataInputStream(s.getInputStream());
            out.writeChar(14 << 8);
            out.flush();
            in.skip(8);
            int response = in.readByte() & 0xff;
            try {
            s.close();
            } catch (Exception e) {}
            return response == 0;
        } catch (Exception e) {
//            e.printStackTrace();
        }
	try {
            s.close();
        } catch (Exception e) {}
	return false;
*/    }

    public static void main(String args[]) {
   //   System.out.println(new GregorianCalendar(2008,2-1,1).getTimeInMillis()/1000);//    1204329600
          new ServerChecker();
    }


}
