
Welcome to Wedge.
=================

The leading bulletin board/forum/blog software for the new Internet.

Nah, just kidding, it's a pretty innocent package that's hardly going to hit the headlines, ever.
Still, it represents about 8 man-years of work from skilled and very dedicated developers.
And it's software to be proud of. Hopefully, it will make your lives a bit less unbearable.

Installing from the GitHub repo
-------------------------------

First, ensure that your server meets the requirements (PHP 5.4, MySQL 5, etc.)

Go to the `github.com/Wedge/wedge` repository.
If you're already there, then hello!

Click the 'Download ZIP' button.
Unzip the file, and upload the resulting folder to your FTP account.
Point your browser to `http://mywebsite.com/my_wedge_folder/index.php`
and follow the instructions.

- If it works, then great.
- If it doesn't, then skip to any fork that works best for you. SMF has an official board that lists them all.
- If you're getting an Error 500 or something similar at install time, it might be due to a configuration error. Ask your host about it. Perhaps switching to a newer version of PHP will help. Alternatively, make sure you don't have a malformed `.htaccess` file in your root folder. It's the cause of 90% of Apache server crashes.

Importing data from my old forum
--------------------------------

- Check out the `Wedge/importer` repo at GitHub. It allows you to import a forum installed on the same server (and, preferably, same MySQL server.)
- Supported source forums include SMF 1.1, SMF 2.0, MyBB 1.6 and a few others that are totally untested. Well, really, only SMF 2.0 import is tested and supported for now.
- Importing Aeva Media items and Custom folder attachments isn't supported for now. Feel free to bother @eurich so that he gets started on these! ;)

[![Download](https://img.shields.io/badge/downloads-importer-brightgreen.svg)](https://github.com/Wedge/importer/)

What's a 'forum' software?
--------------------------

It's like Facebook, but not for dummies.

Or, for a longer definition: Wedge is basically a Community Management System where you post
messages on boards, and others can reply to them. It's revolutionary. It's the future.
Building your own forum requires some technical skills. If you don't have any, you can still
learn from scratch. I did. If you don't feel up to the task, you can use a hosted forum (can't
help you here), or if you already have some hosting space somewhere, a forum system that will take
you by the hand. Commercial software is okay, but usually doesn't have enough to justify its price
tag. Free (as in beer) forums are legion, with one of the most popular being SMF (Simple Machines
Forum). Once you got used to these, you start asking for more. This is where Wedge comes in.

If you think SMF is pretty good but not ambitious enough for its own sake, then you'll
want to try out its forks. Wedge was the first (started in 2010), and one of a couple of realistic
solutions to use. It basically takes everything that we thought sucked in SMF, and rewrites it
how it should have been from the start.

Of course, your opinion may differ. There's this little thing called 'you can't know without
trying', so here it is, in all its installable glory. You can run Wedge side to side with
another forum system, and determine which one suits you best.

What are you waiting for? Friends to talk with? Well, can't help you here.

Credits
-------

Read contributors.txt

What more can you tell me about Wedge?
--------------------------------------

No one can be told you what Wedge really is. You have to read the website for yourself.
Plus, it saves me the hassle. And I'm lazy. See? I can't even come up with my own quotes.

- [![Download](https://img.shields.io/badge/Wedge-Forum-brightgreen.svg)](https://wedge.org)
- [![Download](https://img.shields.io/badge/Wedge-Blog-brightgreen.svg)](https://wedge.org/blog/) (development blog, spectacularly failing at being noob-friendly.)
- [![Download](https://img.shields.io/badge/Pub-Faqs-brightgreen.svg)](https://wedge.org/pub/faq/) (mostly outdated, but we're working on it.)
- [![Download](https://img.shields.io/badge/Pub-Feats-brightgreen.svg)](https://wedge.org/pub/feats/) (a list of features introduced in the first few years.)

Can you read me my rights?
--------------------------

Read license.txt, and if you don't get all of the legalese, it just means:
- Currently, Wedge is free of charge, but it's not free to redistribute. As such, it's
  not 'free and open source' software, but it's definitely open source. If you want to turn
  one of its components into a MIT-licensed generic library, just ask me, I might do it.
- You can't redistribute the package by yourself. If you ever find yourself in the need
  to do so, go to wedge.org and drop a PM to Nao.
- And other details that most people shouldn't have to bother with. Still, be respectful. Thanks.

-- René-Gilles Deberdt (Nao), from Paris, France.
