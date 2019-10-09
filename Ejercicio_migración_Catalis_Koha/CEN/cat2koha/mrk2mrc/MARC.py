# Data classes and a parser for MARC data.

import re

__all__ = [
	'Field', 'ControlField', 'Subfield', 'DataField', 'Record',
	'ParseError', 'Parser', 'MnemParser',
]

DELIMITER="\x1f"
FT="\x1e"	# Field terminator
RT="\x1d"	# Record terminator

# FIXME - These conversions only support those characters that are
# absolutey necessary.  The mnemonics for other characters should
# be added at some point.
char_mapping = [
	('{', '{lcub}'),
	('}', '{rcub}'),
	('$', '{dollar}'),
	('\\', '{bsol}'),
]

spaces = re.compile('  +')
def _to_mnem(s):
	t = ''
	while len(s):
		for subst in char_mapping:
			if s.startswith(subst[0]):
				t += subst[1]
				s = s[len(subst[0]):]
				break
		else:
			m = spaces.match(s)
			if m:
				t += '\\'*len(m.group(0))
				s = s[len(m.group(0)):]
			else:
				t += s[0]
				s = s[1:]
	return t

def _from_mnem(s):
	t = ''
	while len(s):
		for subst in char_mapping:
			if s.startswith(subst[1]):
				t += subst[0]
				s = s[len(subst[1]):]
				break
		else:
			if s.startswith('\\'):
				t += ' '
			else:
				t += s[0]
			s = s[1:]
	return t

class Field:
	def __init__(self, tag=''):
		self.tag=tag.upper()
	def get(self):
		pass
	def get_mnem(self):
		return '='+self.tag+'  '
	def get_values(self, identifier=None):
		return []
	def get_value(self, identifier=None):
		l = self.get_values(identifier)
		if len(l) > 0:
			return l[0]
		else:
			return None

class ControlField(Field):
	def __init__(self, tag='', data=''):
		Field.__init__(self, tag)
		self.data=data
	def get(self):
		return self.data + FT
	def get_mnem(self):
		return Field.get_mnem(self)+_to_mnem(self.data)+'\n'
	def get_values(self, identifier=None):
		if identifier != None:
			return []
		else:
			return [self.data]

class Subfield:
	def __init__(self, i, d):
		self.identifier=i.lower()
		self.data=d
	def get(self):
		return DELIMITER+self.identifier+self.data
	def get_mnem(self):
		return '$'+_to_mnem(self.identifier)+_to_mnem(self.data)

class DataField(Field):
	def __init__(self, tag='', indicators='  '):
		Field.__init__(self, tag)
		self.indicators=indicators
		self.subfields=[]	# list of Subfield
	def get(self):
		s = self.indicators
		for sf in self.subfields:
			s += sf.get()
		return s + FT
	def get_mnem(self):
		s = Field.get_mnem(self)+self.indicators.replace(' ','\\')
		for sf in self.subfields:
			s += sf.get_mnem()
		return s + '\n'
	def get_subfields(self, identifier=None):
		if identifier == None:
			return self.subfields
		else:
			return [sf for sf in self.subfields if sf.identifier==identifier]
	def get_subfield(self, identifier=None):
		l = self.get_subfields(identifier)
		if len(l) > 0:
			return l[0]
		else:
			return None
	def get_values(self, identifier=None):
		return [sf.data for sf in self.get_subfields(identifier)]

class Record:
	default_leader = '00000nam a2200000uu 4500'
	_leader_fields = [
		# (name, type, length, title, required value)
		('length', 'num', 5, 'length', None),
		('status', 'str', 1, 'record status', None),
		('type', 'str', 1, 'record type', None),
		('impl_0708', 'str', 2, 'impl_0708', None),
		('encoding', 'str', 1, 'character encoding', None),
		('nindicators', 'num', 1, 'indicator count', 2),
		('identlen', 'num', 1, 'subfield code length', 2),
		('base_addr', 'num', 5, 'base address of data', None),
		('impl_1719', 'str', 3, 'impl_1719', None),
		('entry_map_length', 'num', 1, 'length-of-field length', 4),
		('entry_map_start', 'num', 1, 'starting-character-position length', 5),
		('entry_map_impl', 'num', 1, 'implementation-defined length', 0),
		('entry_map_undef', 'num', 1, 'undefined entry-map field', 0),
	]
	def __init__(self):
		# Provide a default leader
		self.set_leader(self.default_leader)
		self.fields = []
	def set_leader(self, ldr, lenient=False):
		if lenient:
			ldr = ldr.rstrip()
		if len(ldr) != len(self.default_leader):
			if lenient:
				ldr += self.default_leader[len(ldr):]
				ldr = ldr[:len(self.default_leader)]
			else:
				raise ValueError('wrong leader length')
		for f in Record._leader_fields:
			s = ldr[:f[2]]
			ldr = ldr[f[2]:]
			if f[1] == 'str':
				v = s
			elif f[1] == 'num':
				try:
					v = int(s)
				except ValueError:
					if not lenient:
						raise ValueError('MARC21 requires '+f[3]+' to be numeric')
					else:
						v = 0
			if not lenient and f[4] != None and v != f[4]:
				raise ValueError('MARC21 requires '+f[3]+' of '+`f[4]`)
			self.__dict__[f[0]] = v

	def get_leader(self):
		ldr = ''
		for f in Record._leader_fields:
			if f[1] == 'str':
				s = self.__dict__[f[0]]
			elif f[1] == 'num':
				s = `self.__dict__[f[0]]`.zfill(f[2])
			if len(s) != f[2]:
				raise ValueError(f[0]+' field has incorrect length')
			ldr += s
		assert (len(ldr) == 24)
		return ldr

	def get(self):
		directory = ''
		data = ''
		for f in self.fields:
			d = f.get()
			l = [
				(f.tag, 3, 'tag has wrong length: '+f.tag),
				(`len(d)`, 4, f.tag+' field too long'),
				(`len(data)`, 5, 'record too long'),
			]
			for t in l:
				s = t[0].zfill(t[1])
				if len(s) != t[1]:
					raise ValueError(t[2])
				directory +=s
			data += d
		# 24 is the leader length, 1 for the field terminator
		self.base_addr = 24 + len(directory) + 1
		# 1 for the record terminator
		self.length = self.base_addr + len(data) + 1
		return self.get_leader() + directory + FT + data + RT

	def get_mnem(self):
		s = '=LDR  ' + _to_mnem(self.get_leader()) + '\n'
		for f in self.fields:
			s += f.get_mnem()
		return s + '\n'

	def get_fields(self, tag=None):
		if tag == None:
			return self.fields
		return filter(lambda f: f.tag==tag, self.fields)

	def get_field(self, tag=None):
		l = self.get_fields(tag)
		if len(l) > 0:
			return l[0]
		else:
			return None

	def get_values(self, spec=None):
		l = []
		if spec == None:
			l.append(None)
		else:
			l = spec.split('$')
		if len(l) == 1:
			l.append(None)
		return [ v for f in self.get_fields(l[0]) for v in f.get_values(l[1]) ]

	def get_value(self, spec=None):
		l = self.get_values(spec)
		if len(l) > 0:
			return l[0]
		else:
			return None

class ParseError(Exception):
	def __init__(self, msg, record=None, line=None):
		self.msg = msg
		self.record = record
		self.line = line
	def __str__(self):
		s = ''
		if self.line != None:
			s += 'Line '+`self.line`+': '
		if self.record != None:
			s += 'Record '+`self.record`+': '
		return s + self.msg

class BaseParser:
	def __init__(self, lenient=False):
		self.lenient = lenient
		self.records = []
		self._nrecords = 0
		self._unparsed=''
	def parse(self, unparsed):
		self._unparsed += unparsed
		return self._parse()

	# Must be overridden by derived classes
	def eof(self):
		self._nrecords = 0
		return 0
	def _error(self, s):
		raise ParseError(s)
	def _parse(self):
		return 0

class Parser(BaseParser):
	def eof(self):
		if not self.lenient and len(self._unparsed) > 0:
			raise ParseError('trailing junk or incomplete record at end of file')
		self._nrecords = 0
		return 0
	def _error(self, s):
		raise ParseError(s, self._nrecords)
	def _parse(self):
		old_len = len(self.records)
		while len(self._unparsed) >= 5:
			if not self._unparsed[:5].isdigit():
				self._error("garbled length field")
			rec_len = int(self._unparsed[:5])
			if rec_len < 24:
				self._error("impossibly small length field")
			if len(self._unparsed) < rec_len:
				break
			r = self._parse_record(self._unparsed[:rec_len])
			self.records.append(r)
			self._unparsed = self._unparsed[rec_len:]
		return len(self.records)-old_len

	def _parse_record(self, rec):
		r=Record()
		self._nrecords += 1
		try:
			r.set_leader(rec[:24], self.lenient)
		except ValueError, e:
			self._error("Invalid Leader: "+str(e))

		entries=self._parse_directory(rec[24:r.base_addr])
		base=r.base_addr
		for e in entries:
			f = rec[base+e['start']:base+e['start']+e['length']]
			r.fields.append(self._parse_field(e['tag'], f))
		return r
				

	def _parse_directory(self, directory):
		if not self.lenient and directory[-1]!=FT:
			self._error('directory unterminated')
		directory = directory[:-1]
		emap = {
			'tag': 3,
			'length': 4,
			'start': 5,
		}
		entry_len = emap['tag'] + emap['length'] + emap['start']
		if len(directory) % entry_len != 0:
			self._error('directory is the wrong length')
		entries=[]
		while (len(directory)):
			e = {}
			e['tag'] = directory[:emap['tag']]
			p = emap['tag']
			for f in ['length', 'start']:
				s = directory[p:p+emap[f]]
				if not s.isdigit():
					self._error('non-numeric %s field in directory entry %d' % (f, len(entries)))
				e[f] = int(s)
				p += emap[f]
			entries.append(e)
			directory = directory[p:]
		return entries

	def _parse_field(self, tag, field):
		if not self.lenient and field[-1] != FT:
			self._error('variable field unterminated: '+field)
		field = field[:-1]

		if tag.startswith('00'):
			return ControlField(tag, field)

		# 2 is the number of indicators
		f = DataField(tag, field[:2])
		field = field[2:]

		if len(field) < 1 or field[0] != DELIMITER:
			self._error("missing delimiter in %s field, got '%s' instead" % (f.tag, field))
		# Elements begin with a delimiter, but we treat it as
		# a separator, so the first one will always be empty and
		# is discarded.
		elems = field.split(DELIMITER)[1:]
		# x[:1] is the subfield code
		f.subfields = map(lambda x: Subfield(x[:1], x[1:]), elems)
		return f

class MnemParser(BaseParser):
	def __init__(self, lenient=True):
		self._line = 0
		self._rec = None
		self._field = None
		BaseParser.__init__(self, lenient)
	def _error(self, s):
		raise ParseError(s, self._nrecords, self._line)
	def eof(self):
		self._unparsed += '\n\n'
		n = self._parse()
		self._nrecords = 0
		if self._rec != None:
			self.records.append(self._rec)
			self._rec = None
			return n+1
		return n

	def _parse(self):
		old_len = len(self.records)
		data = self._unparsed
		self._unparsed = ''
		for l in data.splitlines(True):
			if not (l.endswith('\n') or l.endswith('\r')):
				self._unparsed = l
				break

			if l.startswith('#'):
				pass
			elif l.startswith('='):
				self._add_field(self._field)
				self._field = l
			elif l.strip() == '':
				self._add_field(self._field)
				self._field = None
				if self._rec:
					self.records.append(self._rec)
					self._rec = None
			elif not self._field:
				self._error("extra garbage outside of fields")
			else:
				self._field += l
			self._line += 1
		return len(self.records)-old_len

	def _new_record(self):
		self._rec = Record()
		self._nrecords += 1

	def _add_field(self, field):
		if not field:
			return
		if not field.startswith('='):
			self._error("can't happen: non-field data in _field")
		field = field.rstrip('\r\n')		# lose final newline
		if len(field) < 4:
			self._error("field too short")
		tag = field[1:4]
		if field[4:6] != '  ':
			self._error("two spaces must separate the tag from field data")
		if not self._rec:
			self._new_record()

		# Set leader
		if re.match('^(000|LDR)$', tag, re.I):
			try:
				self._rec.set_leader(_from_mnem(field[6:]), self.lenient)
			except ValueError, e:
				self._error("Invalid Leader: "+str(e))
			return

		if tag.startswith('00'):
			f = ControlField(tag, _from_mnem(field[6:]))
		else:
			f = DataField(tag, _from_mnem(field[6:8]))
			data = field[8:]
			# Subfields begin with a delimiter, but we treat it as
			# a separator, so the first one will always be empty (or
			# junk) and is discarded.
			subs = data.split('$')[1:]
			# x[:1] is the subfield code
			f.subfields = map(lambda x: Subfield(x[:1], _from_mnem(x[1:])), subs)
		self._rec.fields.append(f)
		return
			
