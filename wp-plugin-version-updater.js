module.exports.readVersion = function ( contents ) {
	const capturingRegex = /Version:\s*(?<vnum>\d+\.\d+\.\d+)/;
	const found = contents.match( capturingRegex );
	return found.groups.vnum;
};

module.exports.writeVersion = function ( _contents, version ) {
	const regex = /Version:\s*(?<vnum>\d+\.\d+\.\d+)/;
	return _contents.replace( regex, 'Version: ' + version );
};
