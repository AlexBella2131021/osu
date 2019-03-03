create database dotinstall_sns_php1;

grant all on dotinstall_sns_php1.* to dbuser@localhost identified by 'mu4uJsif';

use dotinstall_sns_php1

create table users (
  id int not null auto_increment primary key,
  email varchar(255) unique,
  password varchar(255),
  created datetime,
  modified datetime
);

desc users;