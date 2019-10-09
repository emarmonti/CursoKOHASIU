#!/usr/bin/env python
import os
l=os.listdir(".")
f=open("IDLINK.TXT","w")
for i in l:
	if i =="IDLINK.TXT" or i == "idlink.py":	
		continue
	name=i.split(".")[0]
	s="%s\t%s\n" % (name,i)
	print s
	f.write(s)
f.close()
os.system("zip photo.zip *.jpg IDLINK.TXT")


