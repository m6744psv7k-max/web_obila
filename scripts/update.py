#!/usr/bin/env python3
"""Actualiza data.json con la clasificación/resultados de la FEB y noticias del club."""
import json, re, sys, urllib.request, urllib.parse
import xml.etree.ElementTree as ET
from datetime import datetime, timezone, timedelta
from email.utils import parsedate_to_datetime
from urllib.parse import urljoin
from bs4 import BeautifulSoup

FEB_URL = 'https://www.feb.es/competiciones/resultados/tercerafeb/3/2026'
US = 'HOTEL 4POSTES - ÁVILA AUTÉNTICA - EL BULEVAR'
UA = {'User-Agent': 'Mozilla/5.0 (web-obila-updater)'}

def get(url):
    return urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=40).read().decode('utf-8', 'replace')

def txt(c): return c.get_text(' ', strip=True)

def parse_feb(html):
    soup = BeautifulSoup(html, 'html.parser')
    page = soup.get_text(' ', strip=True)
    t, played, nxt = [], [], []
    for tb in soup.find_all('table'):
        rows = tb.find_all('tr')
        if not rows: continue
        head = [txt(c) for c in rows[0].find_all(['th', 'td'])]
        if 'PJ' in head and 'Equipo' in head:
            for r in rows[1:]:
                c = [txt(x) for x in r.find_all('td')]
                if len(c) >= 7 and c[0].isdigit():
                    t.append([int(c[0]), c[1], int(c[2]), int(c[3]), int(c[4]), int(c[5]), int(c[6])])
        elif 'Equipos' in head and 'Resultado' in head:
            for r in rows[1:]:
                tds = r.find_all('td')
                if len(tds) < 4: continue
                names = [txt(a) for a in tds[0].find_all('a')]
                if len(names) != 2: raise ValueError('equipos no reconocidos: ' + txt(tds[0]))
                res, fecha, hora = txt(tds[1]), txt(tds[2]), txt(tds[3])
                la = tds[1].find('a')
                link = urljoin(FEB_URL, la['href']) if la is not None and la.get('href') else ''
                dm = re.match(r'(\d\d)/(\d\d)', fecha)
                if not dm: continue
                dd = f'{dm.group(1)}/{dm.group(2)}'
                if re.match(r'^\d+\s*-\s*\d+$', res):
                    played.append([names[0], names[1], res.replace(' ', ''), dd, link])
                else:
                    nxt.append([names[0], names[1], f'{dd} · {hora}', link])
    m = re.search(r'Pr[oó]xima Jornada\s+(\d+)', page)
    rnd = int(m.group(1)) if m else None
    # Solo la última jornada jugada (las que aparecen en la tabla de resultados)
    if len(t) < 10 or not played or not nxt or rnd is None or not any(r[1] == US for r in t):
        raise ValueError(f'FEB: datos incompletos (equipos={len(t)}, jugados={len(played)}, próximos={len(nxt)}, jornada={rnd})')
    return {'us': US, 't': t, 'j1': played, 'j2': nxt, 'round': rnd}

def parse_news(xml_text, old, strict=True):
    root = ET.fromstring(xml_text)
    out, limit = [], datetime.now(timezone.utc) - timedelta(days=150)
    for it in root.iter('item'):
        title = (it.findtext('title') or '').strip()
        link = (it.findtext('link') or '').strip()
        src_el = it.find('source')
        src = (src_el.text or '').strip() if src_el is not None else ''
        if src and title.endswith(' - ' + src): title = title[:-(len(src) + 3)]
        if strict and not re.search(r'[óo]bila|4\s?postes', title, re.I): continue
        try: dt = parsedate_to_datetime(it.findtext('pubDate'))
        except Exception: continue
        if dt < limit: continue
        out.append({'date': dt.strftime('%Y-%m-%d'), 'src': src, 'url': link, 'title': title, 'body': ''})
    seen = {n['url'] for n in out}
    out += [n for n in old if n['url'] not in seen]
    out.sort(key=lambda n: n['date'], reverse=True)
    return out[:30]

def main():
    data = json.load(open('data.json', encoding='utf-8'))
    failed = False
    try:
        data['FEB'] = parse_feb(get(FEB_URL))
        print('FEB OK: jornada', data['FEB']['round'])
        data['feb_updated'] = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    except Exception as e:
        failed = True; print('AVISO FEB:', e)
    try:
        q = '("Óbila" OR "Hotel 4Postes") baloncesto'
        url = 'https://news.google.com/rss/search?q=' + urllib.parse.quote(q) + '&hl=es&gl=ES&ceid=ES:es'
        auto = parse_news(get(url), data.get('news_auto', []))
        # Diario de Ávila (sección «Óbila Club de Basket»): todo lo que publique sobre el club
        try:
            q2 = 'site:diariodeavila.es ("Óbila" OR "4Postes" OR "Tercera FEB")'
            url2 = 'https://news.google.com/rss/search?q=' + urllib.parse.quote(q2) + '&hl=es&gl=ES&ceid=ES:es'
            auto = parse_news(get(url2), auto, strict=False)
        except Exception as e:
            print('AVISO Diario de Ávila:', e)
        # no repetir lo que ya está resumido a mano (mismo medio y día)
        manual = {(n['src'], n['date']) for n in data.get('news_manual', [])}
        data['news_auto'] = [n for n in auto if (n['src'], n['date']) not in manual]
        print('Noticias OK:', len(data['news_auto']))
    except Exception as e:
        print('AVISO noticias:', e)
    data['updated'] = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    json.dump(data, open('data.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    sys.exit(1 if failed else 0)

if __name__ == '__main__':
    main()
