import { describe, expect, it } from "vitest"

import { patchBuildGradleForReleaseSigning } from "../../plugins/with-android-release-signing.mts"

const GENERATED_BUILD_GRADLE = `apply plugin: "com.android.application"

android {
    signingConfigs {
        debug {
            storeFile file('debug.keystore')
            storePassword 'android'
            keyAlias 'androiddebugkey'
            keyPassword 'android'
        }
    }
    buildTypes {
        debug {
            signingConfig signingConfigs.debug
        }
        release {
            signingConfig signingConfigs.debug
            minifyEnabled enableMinifyInReleaseBuilds
        }
    }
}
`

describe("patchBuildGradleForReleaseSigning", () => {
  it("adds a release signing config", () => {
    const patched = patchBuildGradleForReleaseSigning(GENERATED_BUILD_GRADLE)

    expect(patched).toContain("signingConfigs {")
    expect(patched).toMatch(/release\s*\{[\s\S]*storeFile releaseKeystoreFile/)
    expect(patched).toContain("file('release.jks')")
    expect(patched).toContain("System.getenv('KEYSTORE_PASSWORD')")
    expect(patched).toContain("System.getenv('KEY_ALIAS')")
    expect(patched).toContain("System.getenv('KEY_PASSWORD')")
  })

  it("points the release build type at the release signing config", () => {
    const patched = patchBuildGradleForReleaseSigning(GENERATED_BUILD_GRADLE)

    expect(patched).toContain("signingConfig signingConfigs.release")
    expect(patched).not.toMatch(
      /release\s*\{[^}]*signingConfig\s+signingConfigs\.debug/,
    )
  })

  it("keeps the debug build type on the debug signing config", () => {
    const patched = patchBuildGradleForReleaseSigning(GENERATED_BUILD_GRADLE)

    expect(patched).toMatch(/debug\s*\{\s*signingConfig signingConfigs\.debug/)
  })

  it("is idempotent", () => {
    const once = patchBuildGradleForReleaseSigning(GENERATED_BUILD_GRADLE)
    const twice = patchBuildGradleForReleaseSigning(once)

    expect(twice).toBe(once)
  })

  it("does not duplicate an existing release signing config", () => {
    const patched = patchBuildGradleForReleaseSigning(GENERATED_BUILD_GRADLE)
    const releaseConfigCount = (patched.match(/releaseKeystoreFile/g) ?? []).length

    // declaration + `.exists()` check + `storeFile` assignment
    expect(releaseConfigCount).toBe(3)
  })
})
