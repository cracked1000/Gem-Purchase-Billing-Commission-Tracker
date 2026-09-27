--
-- PostgreSQL database dump
--

\restrict HDCOGAejMTE7J9vd0YuoebaItFFfQWe3ctZnbaJDa01LnPqNYarzrApPot06Ksb

-- Dumped from database version 17.10 (Homebrew)
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO gemdesk;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO gemdesk;

--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO gemdesk;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO gemdesk;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: invoice_items; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.invoice_items (
    id bigint NOT NULL,
    invoice_id bigint NOT NULL,
    description character varying(255) NOT NULL,
    carats numeric(10,2),
    pieces integer DEFAULT 1 NOT NULL,
    unit_price numeric(14,2) NOT NULL,
    amount numeric(14,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.invoice_items OWNER TO gemdesk;

--
-- Name: invoice_items_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.invoice_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoice_items_id_seq OWNER TO gemdesk;

--
-- Name: invoice_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.invoice_items_id_seq OWNED BY public.invoice_items.id;


--
-- Name: invoices; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.invoices (
    id bigint NOT NULL,
    invoice_number character varying(20),
    seller_id bigint NOT NULL,
    sold_at date NOT NULL,
    commission_percent numeric(5,2) DEFAULT '10'::numeric NOT NULL,
    subtotal numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    commission_amount numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    net_payable numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    total_pieces integer DEFAULT 0 NOT NULL,
    status character varying(10) DEFAULT 'pending'::character varying NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.invoices OWNER TO gemdesk;

--
-- Name: COLUMN invoices.status; Type: COMMENT; Schema: public; Owner: gemdesk
--

COMMENT ON COLUMN public.invoices.status IS 'pending|paid - payout to seller';


--
-- Name: invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoices_id_seq OWNER TO gemdesk;

--
-- Name: invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO gemdesk;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO gemdesk;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO gemdesk;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO gemdesk;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO gemdesk;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO gemdesk;

--
-- Name: sellers; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.sellers (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    phone character varying(32),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sellers OWNER TO gemdesk;

--
-- Name: COLUMN sellers.phone; Type: COMMENT; Schema: public; Owner: gemdesk
--

COMMENT ON COLUMN public.sellers.phone IS 'WhatsApp number';


--
-- Name: sellers_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.sellers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sellers_id_seq OWNER TO gemdesk;

--
-- Name: sellers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.sellers_id_seq OWNED BY public.sellers.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO gemdesk;

--
-- Name: users; Type: TABLE; Schema: public; Owner: gemdesk
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.users OWNER TO gemdesk;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: gemdesk
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO gemdesk;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: gemdesk
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: invoice_items id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoice_items ALTER COLUMN id SET DEFAULT nextval('public.invoice_items_id_seq'::regclass);


--
-- Name: invoices id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: sellers id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.sellers ALTER COLUMN id SET DEFAULT nextval('public.sellers_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: invoice_items; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.invoice_items (id, invoice_id, description, carats, pieces, unit_price, amount, created_at, updated_at) FROM stdin;
3	3	Alexandrite	10.00	1	10000.00	100000.00	2026-08-06 16:45:13	2026-08-06 16:45:13
4	4	Spinel	34.00	1	1000.00	34000.00	2026-08-06 17:12:27	2026-08-06 17:12:27
5	5	Garnet (Hessonite)	21.00	1	2222.00	46662.00	2026-08-06 17:16:20	2026-08-06 17:16:20
\.


--
-- Data for Name: invoices; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.invoices (id, invoice_number, seller_id, sold_at, commission_percent, subtotal, commission_amount, net_payable, total_pieces, status, notes, created_at, updated_at) FROM stdin;
3	0608202601	2	2026-08-06	10.00	100000.00	10000.00	90000.00	1	pending	\N	2026-08-06 16:45:13	2026-08-06 16:45:13
4	0608202602	2	2026-08-06	10.00	34000.00	3400.00	30600.00	1	pending	\N	2026-08-06 17:12:27	2026-08-06 17:12:27
5	0608202603	2	2026-08-06	10.00	46662.00	4666.20	41995.80	1	pending	\N	2026-08-06 17:16:20	2026-08-06 17:16:20
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_07_31_000001_create_sellers_table	1
5	2026_07_31_000002_create_invoices_table	1
6	2026_07_31_000003_create_invoice_items_table	1
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: sellers; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.sellers (id, name, phone, notes, created_at, updated_at) FROM stdin;
2	Shahul hameed	0774101481	\N	2026-08-06 16:44:45	2026-08-06 16:44:45
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
4pXb1uEbOk5P2erJFoFSfuhhSepRwM462cshVPqv	\N	127.0.0.1	Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJ1QTJaYmpJRDhCcWxVNGVmZldNWG9qSHBWR3l6SUxvZURmYkZ2aFdnIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9yZXBvcnRzIiwicm91dGUiOiJyZXBvcnRzLmluZGV4In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=	1786037926
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: gemdesk
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) FROM stdin;
\.


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: invoice_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.invoice_items_id_seq', 5, true);


--
-- Name: invoices_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.invoices_id_seq', 5, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.migrations_id_seq', 6, true);


--
-- Name: sellers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.sellers_id_seq', 2, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: gemdesk
--

SELECT pg_catalog.setval('public.users_id_seq', 1, false);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: invoice_items invoice_items_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_pkey PRIMARY KEY (id);


--
-- Name: invoices invoices_invoice_number_unique; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_invoice_number_unique UNIQUE (invoice_number);


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: sellers sellers_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.sellers
    ADD CONSTRAINT sellers_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: invoice_items_invoice_id_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX invoice_items_invoice_id_index ON public.invoice_items USING btree (invoice_id);


--
-- Name: invoices_sold_at_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX invoices_sold_at_index ON public.invoices USING btree (sold_at);


--
-- Name: invoices_status_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX invoices_status_index ON public.invoices USING btree (status);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: gemdesk
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: invoice_items invoice_items_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoice_items
    ADD CONSTRAINT invoice_items_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE CASCADE;


--
-- Name: invoices invoices_seller_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: gemdesk
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.sellers(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict HDCOGAejMTE7J9vd0YuoebaItFFfQWe3ctZnbaJDa01LnPqNYarzrApPot06Ksb

