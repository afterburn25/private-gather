#!/usr/bin/env python3
from pathlib import Path
import hashlib, json, zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'dist' / 'Private-Gather-1.2.0-or-1.2.1-to-1.2.3-Upgrade.zip'
FILES = [
    '.env.example',
    'VERSION',
    'bootstrap/providers.php',
    'config/age_verification.php',
    'config/release.php',
    'app/Contracts/AgeVerificationProvider.php',
    'app/Support/UsStates.php',
    'app/Support/LifestyleProfileOptions.php',
    'app/Services/Verification/MemberTrust.php',
    'app/Services/Verification/PersonaAgeVerificationProvider.php',
    'app/Providers/MemberTrustServiceProvider.php',
    'app/Http/Middleware/RequireVerifiedForAdditionalClub.php',
    'app/Http/Controllers/ClubMembershipController.php',
    'app/Http/Controllers/Member/AgeVerificationController.php',
    'app/Http/Controllers/Auth/MemberAuthController.php',
    'app/Http/Controllers/Member/ProfileController.php',
    'app/Http/Controllers/HostedDiscoveryController.php',
    'app/Models/User.php',
    'app/Models/ProfilePartnerInvite.php',
    'app/Models/Verification.php',
    'database/migrations/2026_08_22_200000_global_username_and_age_verification_v123.php',
    'resources/views/auth/register.blade.php',
    'resources/views/member/profile.blade.php',
    'resources/views/member/verification.blade.php',
    'resources/views/admin/age-verification.blade.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/member/dashboard.blade.php',
    'resources/views/member/profile-show.blade.php',
    'resources/views/platform/clubs/index.blade.php',
    'resources/views/platform/clubs/show.blade.php',
    'public/assets/private-gather-community-1.2.css',
]
MIGRATION = 'database/migrations/2026_08_22_200000_global_username_and_age_verification_v123.php'

def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()

def main() -> None:
    if (ROOT / 'VERSION').read_text().strip() != '1.2.3':
        raise SystemExit('VERSION must be exactly 1.2.3 before packaging')
    missing = [p for p in FILES if not (ROOT / p).is_file()]
    if missing:
        raise SystemExit('Missing payload files: ' + ', '.join(missing))
    entries = [{'path': p, 'action': 'replace', 'sha256': sha256(ROOT / p)} for p in FILES]
    manifest = {
        'format': 1,
        'product': 'privategather/private-gather',
        'from_versions': ['1.2.0', '1.2.1'],
        'to_version': '1.2.3',
        'minimum_php': '8.3.0',
        'release_name': 'Verified Identity & Global Membership Safety',
        'migrations': [MIGRATION],
        'files': entries,
    }
    OUT.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(OUT, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        z.writestr('manifest.json', json.dumps(manifest, indent=2, sort_keys=True) + '\n')
        for rel in FILES:
            z.write(ROOT / rel, 'payload/' + rel)
    print(OUT)
    print('sha256=' + sha256(OUT))
    print('files=' + str(len(FILES)))

if __name__ == '__main__':
    main()
