# LoL Item Usage Analysis

You can see the demo [here](http://dwhwang.github.io/LoLItemUsageAnalysis/demo.html).

This project is intended for Riot's API Challenge 2.0. 
I couln't finished my whole idea on time, but at least I can show some part of my ideas in the demo.

A few things not implemented yet:

1. worker.php should seperate file writing action to another thread. It's holding the speed down.
2. Planning the query about item vs champion vs buying time vs patch. The data is there, but the problem is how to present it.
3. That front end in the demo page still needs a lot of work...

#### Requirements
- php
  - curl
  - SQLite 3

This is a set of PHP CLI program to get data ready, so it should only be run in command line.

#### file description

Front end

| File                  | descriptions |
| --------------------- | ------------ |
| demo.html             | demo page to show off collected data |
| json/itemdata.json    | prepared data from preparejson.php, this is the main data |
| json/item511.json     | cached 5.11.1 item static json for convience |
| json/item514.json     | cached 5.14.1 item static json for convience |

Back end

| File                  | descriptions |
| --------------------- | ------------ |
| lib/getMatchData.php  | parse and add dataset into queue |
| lib/worker.php        | background task to get match data |
| lib/analyzer.php      | extract data from raw json and store into database |
| lib/ItemAnalyzer.php  | handling SQLite 3 database for main data |
| lib/preparejson.php   | prepare data for front end website |
| lib/SQLiteQueue.php   | handling queue using SQLite 3 |
| lib/config.php        | configuartion file. Your API key goes here. |

#### Attribution

LoL Item Usage Analysis isn't endorsed by Riot Games and doesn't reflect the views or opinions of Riot Games or anyone officially involved in producing or managing League of Legends. League of Legends and Riot Games are trademarks or registered trademarks of Riot Games, Inc. League of Legends © Riot Games, Inc.