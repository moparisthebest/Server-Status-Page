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

package org.moparscape.result;

import java.io.PrintStream;
import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;

public class ResultDelegator {

    public static PrintStream debug = null;

    private static void debug(String s, Object... p) {
        if (debug == null)
            return;
        debug.print("[ResultDelegator]: ");
        debug.printf(s, p);
        debug.println();
    }

    private volatile long numThreads = 0;
    private final ResultProcessor rp;

    public ResultDelegator(Connection conn, String fields, String query, final ResultProcessor rp) throws java.sql.SQLException {
        this.rp = rp;
        Statement stmt = null;
        ResultSet count = null;
        try {
            stmt = conn.createStatement();
            count = stmt.executeQuery("SELECT COUNT(*) AS count " + query);
            long numRows = 0;
            if (count.next())
                numRows = count.getLong("count");
            count.close();
            stmt.close();
            debug("%d rows found", numRows);
            if (numRows == 0)
                return;
            numThreads = (numRows * rp.getMillisPerRow()) / rp.getMillisTimeLimit();
            if (numThreads <= 0)
                numThreads = 1;
            long rowsPerThread = numRows / numThreads;
            // if there is a remainder, start one more thread
            if ((rowsPerThread * numThreads) < numRows)
                ++numThreads;
            debug("%d threads needed", numThreads);
            debug("%d rows per thread", rowsPerThread);
            String queryTemplate = "SELECT %s %s LIMIT %d, " + rowsPerThread;
            for (long x = 0, total = numThreads; x < total; ++x) {
                String subQueryString = String.format(queryTemplate, fields, query, x * rowsPerThread);
                //debug("thread: %d query: %s", x, subQueryString);
                final Statement subStmt = conn.createStatement();
                final ResultSet subQuery = subStmt.executeQuery(subQueryString);
                new Thread() {
                    @Override
                    public void run() {
                        try {
                            rp.process(subQuery);
                        } catch (Throwable e) {
                            e.printStackTrace();
                        } finally {
                            tryClose(subQuery);
                            tryClose(subStmt);
                        }
                        --numThreads;
                    }
                }.start();
            }
        } finally {
            tryClose(count);
            tryClose(stmt);
        }
    }

    public static void tryClose(Connection obj) {
        if (obj != null)
            try {
                obj.close();
            } catch (Throwable e) {
                // do nothing
            }
    }

    public static void tryClose(Statement obj) {
        if (obj != null)
            try {
                obj.close();
            } catch (Throwable e) {
                // do nothing
            }
    }

    public static void tryClose(ResultSet obj) {
        if (obj != null)
            try {
                obj.close();
            } catch (Throwable e) {
                // do nothing
            }
    }

    public void waitFor() throws java.sql.SQLException {
        while (numThreads > 0)
            try {
                Thread.sleep(1000);
            } catch (InterruptedException e) {
                e.printStackTrace();
            }
        rp.finish();
    }
}
