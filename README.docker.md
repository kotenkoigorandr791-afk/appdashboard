# AppDashboard Docker
```bash
git clone https://git.serverkherson.xyz/kotenkoigorandr791/apps-dashboard.git
cd apps-dashboard
docker compose up -d --build
docker compose logs -f appdashboard
```
Open `http://localhost/`.
`docker compose up -d --build` creates both containers: `appdashboard` and `appdashboard-db`.
Database container:
- Service/container: `appdashboard-db`
- Image: `postgres:16`
- Database/user: `appdashboard`
- Host access: `127.0.0.1:15432`
- Data volume: `appdashboard-db-data`
Optional local password override:
```bash
DB_PASSWORD=change_me docker compose up -d --build
```
