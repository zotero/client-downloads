const Promise = require('bluebird');
const rp = require('request-promise');
const url = 'http://localhost:12562/update.php';
const { parseString } = require('xml2js');
const parseXML = Promise.promisify(parseString);
const assert = require('chai').assert;
// Doesn't work properly as of 6/2017: cmp('5.0-beta.202+ddc9989', '5.0-beta.208+0495f2920') == -1
//const vcmp = require('mozilla-version-comparator');
const vcmp = function (a, b) {
	if (a > b) return -1;
	if (b > a) return 1;
	return 0;
}

describe("update.php", function () {
	var req = function (uri) {
		return rp(uri)
		.then(function (response) {
			//console.log(response);
			return parseXML(response);
		});
	};
	
	describe("release channel", function () {
		it("should offer minor update to 4.0.29.15 from earlier 4.0 Mac build", async function () {
			var result = await req(
				url + '/4.0.29.14/20161003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
			assert.equal(result.updates.update[0].$.appVersion, '4.0.29.15');
			// Verify patches
			assert.lengthOf(result.updates.update[0].patch, 2);
			var complete = result.updates.update[0].patch.filter(x => x.$.type == 'complete')[0];
			var partial = result.updates.update[0].patch.filter(x => x.$.type == 'partial')[0];
			assert.match(complete.$.URL, /https:\/\/.+\/4.0.29.15\/Zotero-4.0.29.15-full_mac.mar/);
			assert.match(partial.$.URL, /https:\/\/.+\/4.0.29.15\/Zotero-4.0.29.15-4.0.29.14_mac.mar/);
		});
		
		it("shouldn't offer update for Mac 4.0.29.15 by default", async function () {
			var result = await req(
				url + '/4.0.29.15/20161003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml'
			);
			assert.notOk(result.updates);
		});
		
		it("should offer major 5.0 update for Mac 4.0.29.15 with ?force=1", async function () {
			var result = await req(
				url + '/4.0.29.15/20161003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml?force=1'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'major');
			assert.isAbove(vcmp('5.0', result.updates.update[0].$.appVersion), 0);
			assert.match(result.updates.update[0].$.appVersion, /5\.0\.[\d]+/);
		});
		
		it("should offer minor update to 4.0.29.15 from earlier 4.0 Windows build", async function () {
			var result = await req(
				url + '/4.0.29.10/20160511/WINNT_x86-msvc/en-US/release/Windows_NT%2010.0.0.0%20(x64)/update.xml'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
			assert.equal(result.updates.update[0].$.appVersion, '4.0.29.17');
			
			// Verify patches
			assert.lengthOf(result.updates.update[0].patch, 1);
			var complete = result.updates.update[0].patch.filter(x => x.$.type == 'complete')[0];
			assert.match(complete.$.URL, /https:\/\/.+\/4.0.29.17\/Zotero-4.0.29.17-full_win32.mar/);
		});
		
		it("shouldn't offer update for Windows 4.0.29.17 by default", async function () {
			var result = await req(
				url + '/4.0.29.17/20170119075515/WINNT_x86-msvc-x64/en-US/release/Windows_NT%206.1.1.0%20(x64)/update.xml'
			);
			assert.notOk(result.updates);
		});
		
		it("should offer major 5.0 update for Windows 4.0.29.17 with ?force=1", async function () {
			var result = await req(
				url + '/4.0.29.17/20170119075515/WINNT_x86-msvc-x64/en-US/release/Windows_NT%206.1.1.0%20(x64)/update.xml?force=1'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'major');
			assert.isAbove(vcmp('5.0', result.updates.update[0].$.appVersion), 0);
			assert.match(result.updates.update[0].$.appVersion, /5\.0\.[\d]+/);
		});
		
		it("shouldn't offer update for Linux i686 4.0.29.10", async function () {
			var result = await req(
				url + '/4.0.29.10/20160511/Linux_x86-gcc3/en-US/release/Linux%204.4.0-79-generic%20(GTK%202.24.30)/update.xml'
			);
			assert.notOk(result.updates);
		});
		
		it("shouldn't offer update for Linux x86_64 4.0.29.10 by default", async function () {
			var result = await req(
				url + '/4.0.29.10/20160511/Linux_x86_64-gcc3/en-US/release/Linux%204.4.0-79-generic%20(GTK%202.24.30)/update.xml'
			);
			assert.notOk(result.updates);
		});
		
		it("should offer major 5.0 update for Linux x86_64 4.0.29.10 with ?force=1", async function () {
			var result = await req(
				url + '/4.0.29.10/20160511/Linux_x86_64-gcc3/en-US/release/Linux%204.4.0-79-generic%20(GTK%202.24.30)/update.xml?force=1'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'major');
			assert.isAbove(vcmp('5.0', result.updates.update[0].$.appVersion), 0);
			assert.match(result.updates.update[0].$.appVersion, /5\.0\.[\d]+/);
		});
		
		it("should offer minor update from earlier 5.0 build", async function () {
			var result = await req(
				url + '/5.0/20171003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
			assert.isAbove(vcmp('5.0', result.updates.update[0].$.appVersion), 0);
		});
		
		it("shouldn't show updates past 4.0.29.11 for 10.6-10.8 users", async function () {
			var xml = await rp({
				uri: url + '/4.0.29.11/20160827171848/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2012.6.0/update.xml',
				headers: {
					'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:48.0) Gecko/20100101 Zotero/4.0.29.11'
				}
			});
			var result = await parseXML(xml);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
			assert.equal(result.updates.update[0].$.appVersion, '4.0.29.11');
		});
	});
	
	describe("beta channel", function () {
		it("should offer minor update to latest beta from earlier 5.0 Windows build", async function () {
			var result = await req(
				url + '/5.0-beta.202%2Bddc9989/20170521060737/WINNT_x86-msvc-x64/en-US/beta/Windows_NT%2010.0.0.0%20(x64)/update.xml'
			);
			assert.lengthOf(result.updates.update, 1);
			assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
			assert.isAbove(vcmp('5.0-beta.202+ddc9989', result.updates.update[0].$.appVersion), 0);
			assert.match(result.updates.update[0].$.appVersion, /5\.0\.[\d]+-beta/);
		});
	});
});
