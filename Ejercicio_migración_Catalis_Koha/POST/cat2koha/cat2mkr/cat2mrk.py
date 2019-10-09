# coding: utf-8
# THIS FILE WAS GENERATED USING noweb <http://www.cs.tufts.edu/~nr/noweb/>
# DO NOT MODIFY THIS FILE; GO INSTEAD TO THE REAL SOURCE FILE.

'''
Converts a Catalis bibliographic database in "id format" to MarcMaker format.

The encoding of the original database is latin1; the output is encoded as
utf-8.
'''

import sys
#import os
dbname = sys.argv[1]

#DATA_PATH = os.path.abspath(os.path.join(os.path.dirname(sys.argv[0]), 'testdata'))
#dbpath = '../../bases/'
dbpath = './'
encoding_in = 'latin1'
encoding_out = 'utf-8'

in_file = open('%s%s.id' % (dbpath, dbname))
out_file = open('%s%s.mrk' % (dbpath, dbname), 'w')

for line in in_file:
    if line[:2] == '!v':
        tag = line[2:5]
        if tag > '900' and tag < '920':
            continue
        elif tag < '010':
            data = line[6:]
            out = '=%s  %s' % (tag, data)
        else:
            line = line.decode(encoding_in)
            indicators = line[6:8].replace('#', '\\')
            subfields = line[8:].replace('$', '{dollar}').replace('^', '$')
            out = '=%s  %s%s' % (tag, indicators, subfields)
            out = out.encode(encoding_out)
    else:
        out = '\n'
    out_file.write(out)

out_file.close()


