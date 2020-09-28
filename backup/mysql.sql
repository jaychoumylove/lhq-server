alter table f_user_state
	add day_recommend_count bigint default 0 not null;

alter table f_user_state
	add day_first_count bigint default 0 not null;

alter table f_user_state
	add day_second_count bigint default 0 not null;

