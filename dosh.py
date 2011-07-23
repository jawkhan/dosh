#!/usr/bin/env python
# -*- coding: UTF-8 -*-

import csv
import sqlite3
import re

from datetime import datetime
from hashlib import sha256
from rules import rules, needs_wants_savings_rules, ignore_account_by, account_names

class SqliteHelper(object):
    def __init__(self):
        self.conn = sqlite3.connect('dosh.sqlite')
        self.insert_transaction = '''
            insert or ignore into transactions (
                fingerprint
               ,account_name
               ,transaction_date
               ,description
               ,amount
               ,category
               ,needs_wants_savings
               ,split
            ) values (?,?,?,?,?,?,?,?)
        '''

    def insert_transactions(self, data):
        curs = self.conn.cursor()
        curs.executemany(self.insert_transaction, data)
        self.conn.commit()
        curs.close()
        
    def get_transactions(self):
        curs = self.conn.cursor()
        curs.execute('select id, transaction_date, description, amount, category, account_name from transactions')
        rows = curs.fetchall()
        curs.close()
        return rows
        
    def update_hashes(self, data):
        curs = self.conn.cursor()
        curs.executemany('update transactions set fingerprint = ? where id = ?', data)
        self.conn.commit()
        curs.close()

def create_digest(date, description, amount, account):
    return sha256(','.join([date, re.sub(r'\s', '', description), str(amount), account])).hexdigest()

def parse_self_file(filename):
    pass

def parse_yodlee_export_file(filename):
    r = csv.DictReader(open(filename, 'rb'))
    data = []
    for row in r:
        # Status,Date,Original Description,Split Type,Category,Currency,Amount,User Description,Memo,Classification,Account Name
        # Cleared,17/06/2011,System generated transaction to honor user's Current Balance overwriting action,,Other Income,GBP,158.12,,,Personal,Custom Investments - xxxxxxxxxxxxxx2006

        # Ignore a bunch of stuff
        if ignore_account_by(row['Original Description']): continue
        if ignore_account_by(row['Account Name']): continue
        
        # tweaks
        row['Account Name'] = account_names[row['Account Name']]

        date = datetime.strptime(row['Date'], '%d/%m/%Y').strftime('%Y-%m-%d')
        amount = float(row['Amount'].replace(',', ''))
        digest = create_digest(date, row['Original Description'], amount, row['Account Name'])

        data.append([ \
            digest, \
            row['Account Name'], \
            date, \
            row['Original Description'], \
            amount, \
            row['Category'],
            'Unknown', \
            row['Split Type'], \
        ])
    return data

def parse_hsbc_file(filename):
    # 2011-06-17,ACME INC.,7.50
    r = csv.reader(open(filename, 'rb'))
    data = []
    account_name = account_names['HSBC']
    for row in r:
        date = row[0]
        amount = float(row[-1])
        if len(row) > 3: 
            # must be a comma in the description
            description = ', '.join(row[1:-1])
            print 'Warning, incorrect field count - using joined description', description
        else:
            description = row[1]
            
        digest = create_digest(date, description, amount, account_name)
        data.append([digest, account_name, date, description, amount, 'Unknown', 'Unknown', ''])

    return data

def parse_egg_file(filename):
    # 13 Jun 2011	C2C RAIL LTD-TICKE GB	£38.40 DR
    r = csv.reader(open(filename, 'rb'), delimiter="\t")
    data = []
    account_name = account_names['Egg Card']
    for row in r:
        date = datetime.strptime(row[0], '%d %b %Y').strftime('%Y-%m-%d')
        desc = row[1]
        amount = float(re.search(r'\d+\.\d\d', row[2]).group(0))
        if not row[2].endswith('CR'): # assume debit unless explicitly CR
            amount = -amount;
        digest = create_digest(date, desc, amount, account_name)
        data.append([digest, account_name, date, desc, amount, 'Unknown', 'Unknown', ''])
        
    return data

def parse_nationwide_file(filename):
    r = csv.reader(open(filename, 'rb'))

    # skip to data
    row = r.next() # Account name: ,account_name,,,,
    account_name = account_names[row[1]]

    r.next() # Account balance: ,£103.32,,,,
    r.next() # Available balance: ,£103.32,,,,
    r.next() # Date,Transactions,Debits,Credits,Balance
    r.next() # [blank line]

    data = []
    for row in r:
        # 19 Mar 2011,"Tesco Store 1.",£19.58,,£390.66
        date = datetime.strptime(row[0], '%d %b %Y').strftime('%Y-%m-%d')
        desc = row[1]

        if row[2] == '':
            amount = float(row[3][1:])
        else:
            amount = -float(row[2][1:])

        digest = create_digest(date, desc, amount, account_name)
        data.append([digest, account_name, date, desc, amount, 'Unknown', 'Unknown', ''])

    return data

def find_first(item, matchers):
    for matcher in (m for m in matchers if m.is_match(item)):
        return matcher

def compare_rows(row1, row2):
    # (1, u'2011-06-16', u'Transfer from xxxx1234.', 50, u'Transfers', u'My Bank Account')
    # rows must have same date, same amount, and same normalised description
    if row1[1] != row2[1]: return False
    if row1[3] != row2[3]: return False
    
    desc1 = re.sub(r'\s', '', row1[2])
    desc2 = re.sub(r'\s', '', row2[2])
    if desc1 != desc2: return False
    
    return True
    
def search_for_duplicates(helper):
    rows = helper.get_transactions()
    dups = []
    for idx1, val1 in enumerate(rows):
        for idx2, val2 in enumerate(rows):
            if idx1 == idx2: continue
            if (val1[0], val2[0]) in dups: continue
            if (val2[0], val1[0]) in dups: continue
            
            if compare_rows(val1, val2):
                print "same!", val1[0], val2[0], val1[2], val1[3], val1[1]
                dups.append((val1[0], val2[0]))
            
    return dups

def refingerprint(helper):
    # (1, u'2011-06-16', u'Transfer from xxxx1234.', 50, u'Transfers', u'My Bank Account')
    rows = helper.get_transactions()
    data = []
    for row in rows:
        hash = create_digest(row[1], row[2], row[3], row[5])
        data.append((hash, row[0]))
        
    helper.update_hashes(data)
    
if __name__ == "__main__":
    import sys, getopt, glob

    try:
        opts, args = getopt.getopt(sys.argv[1:],
            "vh:n:e:y:",
            [
                # debugging
                "verbose",
                "testmatch",

                # accounts
                "hsbc=",
                "nationwide=",
                "egg=",
                "yodlee=",
                
                # maintenance
                "dups",
                "rehash",
            ])

    except getopt.GetoptError, ex:
        print ex
        sys.exit(1)

    verbose = 0
    data = []
    testmatch = False
    dups = False
    rehash = False
    for opt, arg in opts:
        # debugging
        if opt in ("-v", "--verbose"):
            verbose += 1
        elif opt in ("-h", "--hsbc"):
            for filename in glob.iglob(arg):
                if verbose: print "parsing", filename
                data.extend(parse_hsbc_file(filename))
        elif opt in ("-e", "--egg"):
            for filename in glob.iglob(arg):
                if verbose: print "parsing", filename
                data.extend(parse_egg_file(filename))
        elif opt in ("-n", "--nationwide"):
            for filename in glob.iglob(arg):
                if verbose: print "parsing", filename
                data.extend(parse_nationwide_file(filename))
        elif opt in ("-y", "--yodlee"):
            for filename in glob.iglob(arg):
                if verbose: print "parsing", filename
                data.extend(parse_yodlee_export_file(filename))
        elif opt == "--testmatch":
            testmatch = True
        elif opt == "--dups":
            dups = True
        elif opt == "--rehash":
            rehash = True

    # apply rules
    for d in data:
        m = find_first(d, rules)
        if m: d[5] = m.category

    if len(data) > 0:
        if testmatch:
            for d in data:
                if d[5] != 'Unknown': print d[3], d[4], d[5]

        else:
            sql = SqliteHelper()
            sql.insert_transactions(data)
    elif dups:
        sql = SqliteHelper()
        found = search_for_duplicates(sql)
        for (i,j) in found:
            print "delete from transactions where id = ", min(i,j), ";"
    elif rehash:
        sql = SqliteHelper()
        refingerprint(sql)
        