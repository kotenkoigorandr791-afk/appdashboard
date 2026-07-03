with open('index.php', 'r', encoding='utf-8') as f:
    content = f.read()

applied = 0

# Fix 1: Add transition:none to overlay
old1 = '.modal-overlay.modal-overlay--api-docs {\n  align-items:stretch; justify-content:stretch; padding:0;\n  background:rgba(0,0,0,.55); backdrop-filter:blur(10px);\n}'
new1 = '.modal-overlay.modal-overlay--api-docs {\n  align-items:stretch; justify-content:stretch; padding:0;\n  background:rgba(0,0,0,.55); backdrop-filter:blur(10px);\n  transition:none;\n}'
if old1 in content:
    content = content.replace(old1, new1, 1)
    applied += 1
    print("1. Added transition:none")
else:
    print("1. NOT FOUND")

# Fix 2: Fix header shadow - theme adaptive
old2 = '  box-shadow:0 1px 4px rgba(0,0,0,.12);'
new2 = '  box-shadow:0 1px 4px color-mix(in srgb, var(--line) 50%, transparent);'
if old2 in content:
    content = content.replace(old2, new2, 1)
    applied += 1
    print("2. Fixed box-shadow")
else:
    print("2. NOT FOUND")

with open('index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print(f"Done! {applied}/2 fixes applied.")
