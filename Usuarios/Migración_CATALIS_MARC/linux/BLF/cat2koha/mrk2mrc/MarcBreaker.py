#!/usr/bin/python
import sys
import MARC

p = MARC.Parser(True)

while 1:
	buf = sys.stdin.read(8192)
	if buf=='':
		break
	if p.parse(buf) > 0:
		for r in p.records:
			sys.stdout.write(r.get_mnem())
		p.records=[]
p.eof()
for r in p.records:
	sys.stdout.write(r.get_mnem())
