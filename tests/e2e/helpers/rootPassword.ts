import { execFileSync } from 'node:child_process';

/**
 * Several specs exercise the *first start* flow, which only exists while the
 * built-in admin still has the factory password (`root`) and the
 * `must_change_password` flag set. The rest of the suite logs in with the
 * provisioned password, so those specs reset the account before they run and
 * hand it back afterwards — otherwise one file poisons the next.
 */
export const FACTORY_ROOT_PASSWORD = 'root';
export const SUITE_ROOT_PASSWORD = process.env.E2E_USER_PASSWORD || '12345T';

function php(code: string) {
  execFileSync('docker', ['exec', 'appdashboard', 'php', '-r', code], { stdio: 'pipe' });
}

export function resetRootToFactoryPassword() {
  php(`
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
$st = $db->prepare("UPDATE users SET password = :p, must_change_password = 1 WHERE email = 'root@root'");
$st->bindValue(":p", appPasswordHash("root"), PDO::PARAM_STR);
$st->execute();
userCacheBust("root@root");
`);
}

export function restoreRootSuitePassword(password: string = SUITE_ROOT_PASSWORD) {
  php(`
define("APP_ROOT", getcwd());
require "db.php";
$db = getDB();
$st = $db->prepare("UPDATE users SET password = :p, must_change_password = 0 WHERE email = 'root@root'");
$st->bindValue(":p", appPasswordHash(${JSON.stringify(password)}), PDO::PARAM_STR);
$st->execute();
userCacheBust("root@root");
`);
}
