# CityCare Railway infrastructure

`railway.ts` defines the production web service, database queue worker,
hourly reminder job, and persistent clinical-attachment volume. Supabase
remains the PostgreSQL provider; Railway does not create another database.

Use Railway CLI 5.45.10 or newer:

```sh
railway config plan
railway config apply
```

`APP_KEY`, `APP_URL`, and `DB_PASSWORD` are deliberately represented by
`preserve()`. Set them through Railway's sealed environment variables and
never commit their values.
