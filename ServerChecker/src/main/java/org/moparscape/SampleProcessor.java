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

import java.sql.ResultSet;
import java.util.Random;

public class SampleProcessor extends AbstractResultProcessor {

    public int count = 0;

    public SampleProcessor(int millisPerRow, int millisTimeLimit) {
        super(millisPerRow, millisTimeLimit);
    }

    public void process(ResultSet r) {
        int myCount = count++;
        System.out.println("in process() count: " + myCount);
        try {
            Thread.sleep(Math.max(this.millisPerRow / 10, new Random().nextInt((int) this.millisPerRow)));
        } catch (Exception e) {
            e.printStackTrace();
        }
        System.out.println("exiting process() count: " + myCount);
    }

    public void finish() {
        // empty
    }
}
