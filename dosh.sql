create table transactions (
    id integer primary key asc
   ,fingerprint text unique
   ,account_name text
   ,transaction_date text
   ,description text
   ,amount numeric
   ,category text
   ,needs_wants_savings text
   ,split text
);

create table groups (
    id integer primary key asc
   ,group_name text 
   ,account_name text 
);

create index transaction_date_idx on transactions(transaction_date);
create index category_idx on transactions(category);
create index split_idx on transactions(split);

create unique index group_account_idx on groups(group_name, account_name);
