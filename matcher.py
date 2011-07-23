#!/usr/bin/env python

import re

class Matcher(object):
    def __init__(self, regex, account=None, amount=None, category='Unknown'):
        self.regex = re.compile(regex)
        self.category = category
        self.account = account
        self.amount = None
        self.op = None
        if amount:
            self.op = amount[0]
            self.amount = float(amount[1:])

    def is_match(self, row):
        if not self.regex.match(row[3]):
            return False

        if self.account:
            if not self.account == row[1]:
                return False

        if self.amount:
            amount = row[4]
            if self.op == '<': return amount < self.amount
            if self.op == '=': return amount == self.amount
            if self.op == '>': return amount > self.amount

        return True
