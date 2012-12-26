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

import org.moparscape.result.AbstractResultProcessor;
import org.moparscape.result.ResultDelegator;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

import static org.moparscape.ServerChecker.getConnection;
import static org.moparscape.ServerChecker.inStatement;
import static org.moparscape.result.ResultDelegator.tryClose;

public abstract class OnlineOfflineProcessor extends AbstractResultProcessor {

    protected final List<Long> online = new ArrayList<Long>();
    protected final List<Long> offline = new ArrayList<Long>();

    private final List<IPUpdate> ipUpdates = new ArrayList<IPUpdate>();

    private final String ipUpdateTable, onlineQuery, offlineQuery;

    protected String finalQuery = null;

    protected OnlineOfflineProcessor(int millisPerRow, int millisTimeLimit, String onlineQuery, String offlineQuery, String ipUpdateTable) {
        super(millisPerRow, millisTimeLimit);
        this.onlineQuery = onlineQuery;
        this.offlineQuery = offlineQuery;
        this.ipUpdateTable = ipUpdateTable;
    }

    public void online(String resolvedIP, Long id, String hostName) {
        // if resolvedIP has changed, update it
        if (resolvedIP != null)
            ipUpdates.add(new IPUpdate(id, resolvedIP));
    }

    public void offline(Long id) {

    }

    public void process(ResultSet result) throws SQLException {
        while (result.next()) {
            Long id = result.getLong("id");

            StringBuilder resolvedIP = null;
            String hostName = result.getString("ip");
            String oldResolvedIP = result.getString("ipaddress");
            //System.out.printf("%s(%s):%d\n", hostName, oldResolvedIP, result.getInt("port"));
            // if it's an actual hostname vs the ip itself
            if (!hostName.equals(oldResolvedIP))
                resolvedIP = new StringBuilder();
            if (ServerChecker.validServer(millisPerRow, hostName, result.getInt("port"), resolvedIP, id, oldResolvedIP)) {
                String newResolvedIP = null;
                // if resolvedIP has changed, update it
                if (resolvedIP != null && !oldResolvedIP.equals(resolvedIP.toString()))
                    newResolvedIP = resolvedIP.toString();
                online(newResolvedIP, id, hostName);
            } else {
                offline(id);
            }
        }
    }

    public void finish() {
        Connection conn = null;
        Statement stmt = null;
        try {
            conn = getConnection();
            conn.setAutoCommit(false);
            stmt = conn.prepareStatement(String.format("UPDATE `%s` SET `ipaddress` = ? WHERE `id` = ?", this.ipUpdateTable));
            if (!ipUpdates.isEmpty()) {
                PreparedStatement ps = (PreparedStatement) stmt;
                for (IPUpdate update : ipUpdates) {
                    ps.setString(1, update.resolvedIP);
                    ps.setLong(2, update.id);
                    ps.execute();
                }
            }
            if (!online.isEmpty())
                stmt.execute(onlineQuery + inStatement(online));
            if (!offline.isEmpty())
                stmt.execute(offlineQuery + inStatement(offline));
            if (finalQuery != null)
                stmt.execute(finalQuery);
            conn.commit();
        } catch (Throwable e) {
            e.printStackTrace();
        } finally {
            tryClose(stmt);
            ResultDelegator.tryClose(conn);
        }
        ipUpdates.clear();
    }

    protected static class IPUpdate {
        public final String resolvedIP;
        public final Long id;

        protected IPUpdate(Long id, String resolvedIP) {
            this.id = id;
            this.resolvedIP = resolvedIP;
        }
    }
}
