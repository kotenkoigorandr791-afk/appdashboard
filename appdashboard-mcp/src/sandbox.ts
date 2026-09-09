import { shellAction } from "./api.js";

export interface SandboxedScript {
  id: string;
  name: string;
  filename: string;
  content: string;
  language: "bash" | "python" | "php" | "javascript";
  description?: string;
  created_at: number;
  created_by: string;
}

export interface ScriptExecutionResult {
  success: boolean;
  stdout: string;
  stderr: string;
  exit_code: number;
  execution_time_ms?: number;
  error?: string;
}

export interface ListScriptsResponse {
  scripts: SandboxedScript[];
  total: number;
}

export class SandboxError extends Error {
  constructor(message: string, public code: string = "sandbox_error") {
    super(message);
    this.name = "SandboxError";
  }
}

class SandboxStorage {
  private scripts = new Map<string, SandboxedScript>();

  saveScript(script: SandboxedScript): void {
    this.scripts.set(script.id, script);
  }

  getScript(id: string): SandboxedScript | undefined {
    return this.scripts.get(id);
  }

  listScripts(): SandboxedScript[] {
    return Array.from(this.scripts.values()).sort((a, b) => b.created_at - a.created_at);
  }

  deleteScript(id: string): boolean {
    return this.scripts.delete(id);
  }

  exists(id: string): boolean {
    return this.scripts.has(id);
  }
}

const sandboxStorage = new SandboxStorage();

export async function listSandboxScripts(): Promise<ListScriptsResponse> {
  const scripts = sandboxStorage.listScripts();
  return { scripts, total: scripts.length };
}

export async function getSandboxScript(id: string): Promise<SandboxedScript> {
  const script = sandboxStorage.getScript(id);
  if (!script) {
    throw new SandboxError(`Script not found: ${id}`, "script_not_found");
  }
  return script;
}

export async function createSandboxScript(script: Omit<SandboxedScript, "id" | "created_at">): Promise<SandboxedScript> {
  const id = `script_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
  const newScript: SandboxedScript = {
    ...script,
    id,
    created_at: Date.now(),
    created_by: "system",
  };

  await shellAction("sandbox_save_script", newScript as unknown as Record<string, unknown>);
  sandboxStorage.saveScript(newScript);

  return newScript;
}

export async function updateSandboxScript(id: string, updates: Partial<Omit<SandboxedScript, "id" | "created_at" | "created_by">>): Promise<SandboxedScript> {
  const script = sandboxStorage.getScript(id);
  if (!script) {
    throw new SandboxError(`Script not found: ${id}`, "script_not_found");
  }

  const updated = { ...script, ...updates };
  await shellAction("sandbox_update_script", updated);
  sandboxStorage.saveScript(updated);

  return updated;
}

export async function deleteSandboxScript(id: string): Promise<void> {
  const script = sandboxStorage.getScript(id);
  if (!script) {
    throw new SandboxError(`Script not found: ${id}`, "script_not_found");
  }

  await shellAction("sandbox_delete_script", { id });
  sandboxStorage.deleteScript(id);
}

export async function executeSandboxScript(
  id: string,
  params: Record<string, unknown> = {}
): Promise<ScriptExecutionResult> {
  const script = await getSandboxScript(id);

  const startTime = Date.now();
  let result: ScriptExecutionResult;

  try {
    switch (script.language) {
      case "bash":
        result = await executeBashScript(script, params);
        break;
      case "python":
        result = await executePythonScript(script, params);
        break;
      case "php":
        result = await executePhpScript(script, params);
        break;
      case "javascript":
        result = await executeJavaScriptScript(script, params);
        break;
      default:
        throw new SandboxError(`Unsupported language: ${script.language}`, "unsupported_language");
    }

    result.execution_time_ms = Date.now() - startTime;

    if (result.exit_code !== undefined && result.exit_code !== 0 && result.exit_code !== null) {
      throw new SandboxError(`Script execution failed with exit code ${result.exit_code}`, "execution_failed");
    }

    return result;
  } catch (err) {
    return {
      success: false,
      stdout: "",
      stderr: err instanceof Error ? err.message : String(err),
      exit_code: -1,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}

async function executeBashScript(script: SandboxedScript, params: Record<string, unknown>): Promise<ScriptExecutionResult> {
  const { execSync } = await import("child_process");
  const safeParams = sanitizeParams(params);
  const cmd = replaceParams(script.content, safeParams);

  try {
    const result = execSync(cmd, {
      timeout: 30000,
      maxBuffer: 10 * 1024 * 1024,
      shell: "/bin/bash",
      env: {
        ...process.env,
        ...sanitizeParamsForEnv(params),
        PATH: "/usr/local/bin:/usr/bin:/bin",
      },
      cwd: "/tmp",
      uid: 65534,
      gid: 65534,
    });

    const resultAny = result as any;
    const stdout = typeof resultAny.stdout === "string" ? resultAny.stdout : "";
    const stderr = typeof resultAny.stderr === "string" ? resultAny.stderr : "";
    const status = resultAny.status ?? 0;

    return {
      success: status === 0,
      stdout: stdout.trim(),
      stderr: stderr.trim(),
      exit_code: status,
    };
  } catch (error: any) {
    const result = error as { stdout?: string; stderr?: string; status?: number };
    const stdout = typeof result.stdout === "string" ? result.stdout : "";
    const stderr = typeof result.stderr === "string" ? result.stderr : "";
    const status = result.status ?? -1;

    return {
      success: false,
      stdout: stdout.trim(),
      stderr: stderr.trim() || error.message || "",
      exit_code: status,
    };
  }
}

async function executePythonScript(script: SandboxedScript, params: Record<string, unknown>): Promise<ScriptExecutionResult> {
  const { execSync } = await import("child_process");
  const safeParams = sanitizeParams(params);
  const cmd = replaceParams(script.content, safeParams);

  try {
    const result = execSync(cmd, {
      timeout: 30000,
      maxBuffer: 10 * 1024 * 1024,
      env: {
        ...process.env,
        ...sanitizeParamsForEnv(params),
        PATH: "/usr/local/bin:/usr/bin:/bin",
        HOME: "/tmp",
      },
      cwd: "/tmp",
      uid: 65534,
      gid: 65534,
    });

    const resultAny = result as any;
    const stdout = typeof resultAny.stdout === "string" ? resultAny.stdout : "";
    const stderr = typeof resultAny.stderr === "string" ? resultAny.stderr : "";
    const status = resultAny.status ?? 0;

    return {
      success: status === 0,
      stdout: stdout.trim(),
      stderr: stderr.trim(),
      exit_code: status,
    };
  } catch (error: any) {
    const result = error as { stdout?: string; stderr?: string; status?: number };
    const stdout = typeof result.stdout === "string" ? result.stdout : "";
    const stderr = typeof result.stderr === "string" ? result.stderr : "";
    const status = result.status ?? -1;

    return {
      success: false,
      stdout: stdout.trim(),
      stderr: stderr.trim() || error.message || "",
      exit_code: status,
    };
  }
}

async function executePhpScript(script: SandboxedScript, params: Record<string, unknown>): Promise<ScriptExecutionResult> {
  const { execSync } = await import("child_process");
  const safeParams = sanitizeParams(params);
  const cmd = replaceParams(script.content, safeParams);

  try {
    const result = execSync(cmd, {
      timeout: 30000,
      maxBuffer: 10 * 1024 * 1024,
      env: {
        ...process.env,
        ...sanitizeParamsForEnv(params),
        PATH: "/usr/local/bin:/usr/bin:/bin",
        HOME: "/tmp",
      },
      cwd: "/tmp",
      uid: 65534,
      gid: 65534,
    });

    const resultAny = result as any;
    const stdout = typeof resultAny.stdout === "string" ? resultAny.stdout : "";
    const stderr = typeof resultAny.stderr === "string" ? resultAny.stderr : "";
    const status = resultAny.status ?? 0;

    return {
      success: status === 0,
      stdout: stdout.trim(),
      stderr: stderr.trim(),
      exit_code: status,
    };
  } catch (error: any) {
    const result = error as { stdout?: string; stderr?: string; status?: number };
    const stdout = typeof result.stdout === "string" ? result.stdout : "";
    const stderr = typeof result.stderr === "string" ? result.stderr : "";
    const status = result.status ?? -1;

    return {
      success: false,
      stdout: stdout.trim(),
      stderr: stderr.trim() || error.message || "",
      exit_code: status,
    };
  }
}

async function executeJavaScriptScript(script: SandboxedScript, params: Record<string, unknown>): Promise<ScriptExecutionResult> {
  const { execSync } = await import("child_process");
  const safeParams = sanitizeParams(params);
  const cmd = replaceParams(script.content, safeParams);

  try {
    const result = execSync(cmd, {
      timeout: 30000,
      maxBuffer: 10 * 1024 * 1024,
      env: {
        ...process.env,
        ...sanitizeParamsForEnv(params),
        PATH: "/usr/local/bin:/usr/bin:/bin",
        HOME: "/tmp",
      },
      cwd: "/tmp",
      uid: 65534,
      gid: 65534,
    });

    const resultAny = result as any;
    const stdout = typeof resultAny.stdout === "string" ? resultAny.stdout : "";
    const stderr = typeof resultAny.stderr === "string" ? resultAny.stderr : "";
    const status = resultAny.status ?? 0;

    return {
      success: status === 0,
      stdout: stdout.trim(),
      stderr: stderr.trim(),
      exit_code: status,
    };
  } catch (error: any) {
    const result = error as { stdout?: string; stderr?: string; status?: number };
    const stdout = typeof result.stdout === "string" ? result.stdout : "";
    const stderr = typeof result.stderr === "string" ? result.stderr : "";
    const status = result.status ?? -1;

    return {
      success: false,
      stdout: stdout.trim(),
      stderr: stderr.trim() || error.message || "",
      exit_code: status,
    };
  }
}

async function executeProcess(
  command: string,
  params: Record<string, unknown>
): Promise<ScriptExecutionResult> {
  const { spawnSync } = await import("child_process");

  const parts = command.trim().split(/\s+/);
  const execPath = parts[0];
  const args = parts.slice(1);

  try {
    const result = spawnSync(execPath, args, {
      timeout: 30000,
      env: {
        ...process.env,
        ...sanitizeParamsForEnv(params),
        PATH: "/usr/local/bin:/usr/bin:/bin",
        HOME: "/tmp",
      },
      cwd: "/tmp",
      uid: 65534,
      gid: 65534,
      stdio: ["ignore", "pipe", "pipe"],
    });

    const stdout = typeof result.stdout === "string" ? result.stdout : "";
    const stderr = typeof result.stderr === "string" ? result.stderr : "";

    return {
      success: result.status === 0,
      stdout: stdout.trim(),
      stderr: stderr.trim(),
      exit_code: result.status || 0,
    };
  } catch (error: any) {
    return {
      success: false,
      stdout: "",
      stderr: error.message || "",
      exit_code: -1,
    };
  }
}

function sanitizeParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  const forbiddenChars = /[;&|`$()<>|"'\n\r\t]|\\|;/g;

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue;

    const strValue = String(value);

    result[key] = strValue
      .replace(/[;&|`$()<>|"'\n\r\t\\]/g, "")
      .replace(/\s+/g, "_");
  }

  return result;
}

function sanitizeParamsForEnv(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  const forbiddenChars = /[;&|`$()<>|"'\n\r\t]|\\|;/g;

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue;

    const strValue = String(value);

    result[key.toUpperCase().replace(/\s+/g, "_")] = strValue
      .replace(/[;&|`$()<>|"'\n\r\t\\]/g, "")
      .replace(/\s+/g, "_");
  }

  return result;
}

function replaceParams(content: string, params: Record<string, string>): string {
  let result = content;

  for (const [key, value] of Object.entries(params)) {
    result = result.replace(new RegExp(`\\$\\{${key}\\}`, "g"), value);
    result = result.replace(new RegExp(`\\$${key}`, "g"), value);
  }

  return result;
}

export function getSupportedLanguages(): string[] {
  return ["bash", "python", "php", "javascript"];
}

export function isSandboxError(error: unknown): error is SandboxError {
  return error instanceof SandboxError;
}
