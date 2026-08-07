import glob

for file in glob.glob('public/*.php') + glob.glob('*.php'):
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    if "ws = new WebSocket('ws://' + ip + '/ws');" in content:
        content = content.replace("ws = new WebSocket('ws://' + ip + '/ws');", "ws = new WebSocket('ws://' + ip + ':81/');")
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Updated {file}')
