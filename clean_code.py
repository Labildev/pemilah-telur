import re

def clean_esp32():
    with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Change BACKEND_URL to const char*
    content = re.sub(r'char BACKEND_URL\[128\] = [^;]+;', 'const char *BACKEND_URL = "http://10.41.198.11/pemilah-telur/api/sort-result.php";', content)

    # 2. Remove HTML string
    html_pattern = r'// ---------------- HALAMAN WEB ----------------.*?\)HTML\";'
    content = re.sub(html_pattern, '', content, flags=re.DOTALL)

    # 3. Remove doc["backend_url"] = BACKEND_URL;
    content = re.sub(r'\s*doc\["backend_url"\] = BACKEND_URL;', '', content)

    # 4. Remove preferences.putString("backend_url", BACKEND_URL);
    content = re.sub(r'\s*preferences\.putString\("backend_url", BACKEND_URL\);', '', content)

    # 5. Remove save_backend_url action block
    ws_action = r'\} else if \(action == "save_backend_url"\) \{.*?^\s*\}'
    content = re.sub(ws_action, '}', content, flags=re.DOTALL | re.MULTILINE)

    # 6. Remove loading backend_url in setup()
    load_url = r'\s*// Load backend URL.*?\s*savedUrl\.toCharArray\(BACKEND_URL, sizeof\(BACKEND_URL\)\);'
    content = re.sub(load_url, '', content, flags=re.DOTALL)

    # 7. Remove server.on("/", ...)
    server_on = r'\s*server\.on\("/", HTTP_GET, \[\]\(AsyncWebServerRequest \*request\) \{\s*request->send_P\(200, "text/html", INDEX_HTML\);\s*\}\);'
    content = re.sub(server_on, '', content, flags=re.DOTALL)

    with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'w', encoding='utf-8') as f:
        f.write(content)

def clean_web(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Remove Backend URL Config card
    panel_pattern = r'<!-- Backend URL Config -->.*?</div>\s*</section><!-- end kolom kiri -->'
    content = re.sub(panel_pattern, '</section><!-- end kolom kiri -->', content, flags=re.DOTALL)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

clean_esp32()
clean_web('public/test.php')
clean_web('public/demo.php')
print('Cleanup done.')
