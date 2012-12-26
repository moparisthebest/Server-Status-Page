/*
 * MoparScape.org server status page
 * Copyright (C) 2012  Travis Burtrum (moparisthebest)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

package org.moparscape;

import org.moparscape.result.ResultDelegator;

import java.io.Closeable;
import java.net.InetSocketAddress;
import java.net.Socket;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Collection;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;

public class ServerChecker {

    //private static final Map<String, String> ipToHostname = Collections.synchronizedMap(new HashMap<String, String>(1000));
    private static final Map<String, StringBuilder> ipToHostname = new HashMap<String, StringBuilder>(1000);

    // 8 seconds
    public static final int millisPerRow = 8 * 1000;

    // 10 minutes
    private static final int millisTimeLimitUpdate = 10 * 60 * 1000;
    // 5 minutes
    private static final int millisTimeLimitNew = 5 * 60 * 1000;

    private static boolean newServers = false;

    private static String databaseUrl;

    private ServerChecker() {
        // make sure nothing instantiates us
    }

    private static void startChecks(String databaseUrl) {
        ServerChecker.databaseUrl = databaseUrl;
        printToLog("STARTING...");
        Connection conn = null;
        try {
            Class.forName("com.mysql.jdbc.Driver").newInstance();
            conn = getConnection();

            String fields = "`id`, `ip`, `port`, `ipaddress`";
            ResultDelegator updateServers = new ResultDelegator(conn, fields, "from `servers` where sponsored = '0' ORDER BY `servers`.`id` ASC", new UpdateProcessor(millisPerRow, millisTimeLimitUpdate));
            updateServers.waitFor();
            printToLog("updateServers completed!");

            newServers = true;
            ResultDelegator newServers = new ResultDelegator(conn, fields, "from `toadd` WHERE `verified` = '1' ORDER BY `id` ASC", new NewProcessor(millisPerRow, millisTimeLimitNew));
            newServers.waitFor();
            printToLog("newServers completed!");

            ResultDelegator.tryClose(conn);

            for (Map.Entry<String, StringBuilder> entry : ipToHostname.entrySet()) {
                String newIP = entry.getKey();
                String[] servers = entry.getValue().toString().split(";");
                if (servers.length == 1)
                    continue;
                String[][] serverInfo = new String[servers.length][];
                System.out.printf("resolvedIP:         '%s'\n", newIP);
                for (int x = 0; x < servers.length; ++x) {
                    serverInfo[x] = servers[x].split(",");
                    System.out.printf("\t\tid:         '%s'\n\t\tnewServer:  '%s'\n\t\thostName:   '%s'\n\t\tport:       '%s'\n\t\tonline:     '%s'\n\t\tpreviousIP: '%s'\n\n", serverInfo[x][0], serverInfo[x][1], serverInfo[x][2], serverInfo[x][3], serverInfo[x][4], serverInfo[x][5]);
                }
                System.out.println("------------------------------------------");
                //System.out.printf("key: '%s' value: '%s'\n", newIP, entry.getValue());

            }

            printToLog("FINISHED SUCCESSFULLY!!");
        } catch (Throwable e) {
            printToLog("ERROR!!");
            e.printStackTrace();
        } finally {
            ResultDelegator.tryClose(conn);
        }
    }

    private static boolean returnClose(boolean ret, String resolvedIP, String hostName, int port, Long id, String oldResolvedIP) {
        // populate ipToHostname
        if (resolvedIP != null)
            synchronized (ipToHostname) {
                String entry = String.format("%s,%b,%s,%d,%b,%s", id, newServers, hostName, port, ret, oldResolvedIP);
                if (ipToHostname.containsKey(resolvedIP)) {
                    ipToHostname.get(resolvedIP).append(";").append(entry);
                    //return false;
                } else
                    ipToHostname.put(resolvedIP, new StringBuilder(entry));
            }
        return ret;
    }

    static boolean validServer(int timeout, String hostName, int port, StringBuilder resolved, Long id, String oldResolvedIP) {
        //System.out.printf("%s:%d\n", ip, port);
        Socket s = null;
        String resolvedIP = null;
        try {
            InetSocketAddress addy = new InetSocketAddress(hostName, port);
            if (addy.isUnresolved())
                return false;
            resolvedIP = addy.getAddress().getHostAddress();
            //System.out.printf("%s(%s):%d\n", ip, resolvedIP, port);
            if (resolved != null)
                resolved.append(resolvedIP);

            // uncomment for testing without spamming packets
            if (true) return returnClose(java.lang.Math.random() < 0.5D, resolvedIP, hostName, port, id, oldResolvedIP);

            s = new Socket();
            s.setSoTimeout(timeout);
            s.connect(addy, timeout);
            /*
            DataOutputStream out = null;
            DataInputStream in = null;
            try {
                out = new DataOutputStream(s.getOutputStream());
                in = new DataInputStream(s.getInputStream());
                out.writeChar(14 << 8);
                out.flush();
                in.skip(8);
                int response = in.readByte() & 0xff;
                return returnClose(response == 0, resolvedIP, hostName, port, id, oldResolvedIP);
            } finally {
                tryClose(out);
                tryClose(in);
            }
            */
            return returnClose(true, resolvedIP, hostName, port, id, oldResolvedIP);
        } catch (Exception e) {
            //e.printStackTrace();
            return returnClose(false, resolvedIP, hostName, port, id, oldResolvedIP);
        } finally {
            tryClose(s);
        }
    }

    static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(databaseUrl);
    }

    static String inStatement(Collection<?> list) {
        if (list == null || list.isEmpty())
            return "()";
        StringBuilder sb = new StringBuilder("(");
        boolean notFirst = false;
        for (Object o : list) {
            if (notFirst) sb.append(",");
            else notFirst = true;
            sb.append(o);
        }
        return sb.append(")").toString();
    }

    public static void main(String[] args) {
        if (args.length < 1) {
            printToLog("Usage: ServerChecker \"jdbc:mysql://localhost:3306/dbname?user=user&password=pass\" [debug]");
            return;
        }
        if (args.length > 1)
            ResultDelegator.debug = System.out;
        startChecks(args[0]);
    }

    static void printToLog(String msg) {
        System.out.printf("%s - %s\n", new Date(), msg);
    }

    public static void tryClose(Socket obj) {
        if (obj != null)
            try {
                obj.close();
            } catch (Throwable e) {
                // do nothing
            }
    }

    public static void tryClose(Closeable obj) {
        if (obj != null)
            try {
                obj.close();
            } catch (Throwable e) {
                // do nothing
            }
    }

}
