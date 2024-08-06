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

describe("Updates", function () {
	var req = function (uri) {
		return rp({
			uri,
			resolveWithFullResponse: true
		})
		.then(function (res) {
			if (res.statusCode !== 200 && res.statusCode !== 302) {
				console.log(res.body);
			}
			assert.include([200, 302], res.statusCode);
			return parseXML(res.body);
		});
	};
	
	describe("release channel", function () {
		describe("Mac", function () {
			it("shouldn't offer update for 6.0.37 by default", async function () {
				var result = await req(
					url + '/6.0.37/20240319052808/Darwin_x86_64-gcc3/en-US/release/Darwin%2023.5.0/update.xml'
				);
				assert.notOk(result.updates);
			});
			
			it("should offer major update to 7.0 for 6.0.37 with ?force=1", async function () {
				var result = await req(
					url + '/4.0.29.15/20161003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml?force=1'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.isAtLeast(vcmp('7.0', result.updates.update[0].$.appVersion), 0);
				//assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+/);
				assert.match(result.updates.update[0].$.appVersion, /7\.0/);
			});
			
			it("should offer minor update from earlier 6.0 build", async function () {
				var result = await req(
					url + '/6.0/20171003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.isAbove(vcmp('6.0', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('7.0', result.updates.update[0].$.appVersion), 0);
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
			
			it("shouldn't show updates past Zotero 6 for 10.11 users", async function () {
				var xml = await rp({
					uri: url + '/6.0.25/20230420171544/Darwin_x86_64-gcc3/en-US/release/Darwin%2015.6.0/update.xml?force=1',
					headers: {
						'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:60.0) Gecko/20100101 Zotero/6.0.25'
					}
				});
				var result = await parseXML(xml);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.match(result.updates.update[0].$.appVersion, /6\.0\.[\d]+/);
			});
			
			it("should show updates past Zotero 6 for 10.12 users", async function () {
				var xml = await rp({
					uri: url + '/6.0.25/20230420171544/Darwin_x86_64-gcc3/en-US/release/Darwin%2015.6.0/update.xml?force=1',
					headers: {
						'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:60.0) Gecko/20100101 Zotero/6.0.25'
					}
				});
				var result = await parseXML(xml);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.isAtLeast(vcmp('7.0', result.updates.update[0].$.appVersion), 0);
				//assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+/);
				assert.match(result.updates.update[0].$.appVersion, /7\.0/);
			});
		});
		
		describe("Windows", function () {
			it("shouldn't offer update for 6.0.36 by default", async function () {
				var result = await req(
					url + '/6.0.36/20240313202508/WINNT_x86-msvc-x86/en-US/release/Windows_NT%206.1.1.0%20(x64)/update.xml'
				);
				assert.notOk(result.updates);
			});
			
			it("should offer major update to 7.0 for 6.0.36 with ?force=1", async function () {
				var result = await req(
					url + '/6.0.36/20240313202508/WINNT_x86-msvc-x86/en-US/release/Windows_NT%2010.0.0.0%20(x86)/update.xml?force=1'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.isAtLeast(vcmp('7.0', result.updates.update[0].$.appVersion), 0);
				
				// Verify patches
				assert.lengthOf(result.updates.update[0].patch, 2);
				var complete = result.updates.update[0].patch.filter(x => x.$.type == 'complete')[0];
				//assert.match(complete.$.URL, /https:\/\/.+\/7\.0\.\d+\/Zotero-7\.0\.\d+-full_bz_win32.mar/);
				assert.match(complete.$.URL, /https:\/\/.+\/7\.0\/Zotero-7\.0-full_bz_win32.mar/);
			});
			
			it("should offer minor update to 5.0.77 for Zotero 5 on Windows XP", async function () {
				var result = await req(
					url + '/5.0.74/20190822031702/WINNT_x86-msvc-x86/en-US/release/Windows_NT%205.1.3.0%20(x86)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.equal(result.updates.update[0].$.appVersion, '5.0.77');
			});
			
			it("should offer major update to 5.0.77 for Zotero 4 on Windows XP", async function () {
				var result = await req(
					url + '/4.0.74/20190822031702/WINNT_x86-msvc-x86/en-US/release/Windows_NT%205.1.3.0%20(x86)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.equal(result.updates.update[0].$.appVersion, '5.0.77');
			});
		});
		
		describe("Linux", function () {
			it("shouldn't offer update for 6.0.35 by default", async function () {
				var result = await req(
					url + '/6.0.35/20160511/Linux_x86_64-gcc3/en-US/release/Linux%204.4.0-79-generic%20(GTK%202.24.30)/update.xml'
				);
				assert.notOk(result.updates);
			});
			
			it("should offer major update to 7.0 for 6.0.35 with ?force=1", async function () {
				var result = await req(
					url + '/6.0.35/20160511/Linux_x86_64-gcc3/en-US/release/Linux%204.4.0-79-generic%20(GTK%202.24.30)/update.xml?force=1'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.isAtLeast(vcmp('7.0', result.updates.update[0].$.appVersion), 0);
				//assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+/);
				assert.match(result.updates.update[0].$.appVersion, /7\.0/);
			});
		});
	});
	
	describe("beta channel", function () {
		describe("Mac", function () {
			it("should offer major update to 7.0 beta from 6.0 build", async function () {
				var result = await req(
					url + '/6.0-beta.202%2Baaaa/20230501021418/Darwin_x86_64-gcc3/en-US/beta/Darwin%2022.4.0/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.isAbove(vcmp('7.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
				assert.include(result.updates.update[0].patch[0].$.URL, '_bz_');
			});
			
			it("should offer minor update to 7.0 beta from earlier 7.0 build", async function () {
				var result = await req(
					url + '/7.0.0-beta.1%2Baaaaaaaaa/20230501021418/Darwin_x86_64-gcc3/en-US/beta/Darwin%2022.4.0/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.isAbove(vcmp('7.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
			});
		});
		
		describe("Windows 64-bit", function () {
			it("should offer minor update to 7.0 beta from earlier 7.0 build", async function () {
				var result = await req(
					url + '/7.0.0-beta.1%2Baaaaaaaa/20230304083433/WINNT_x86_64-msvc-x64/en-US/beta/Windows_NT%2010.0.0.0.22000.1574%20(x64)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.isAbove(vcmp('7.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
			});
			
			it("should offer major update to 7.0 beta from 6.0 build", async function () {
				var result = await req(
					url + '/6.0-beta.202%2Bddc9989/20170521060737/WINNT_x86-msvc-x64/en-US/beta/Windows_NT%2010.0.0.0%20(x64)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.isAbove(vcmp('7.0.0-beta.1+aaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
			});
		});
		
		describe("Windows 32-bit", function () {
			it("should offer minor update to 7.0 beta from earlier 7.0 build", async function () {
				var result = await req(
					url + '/7.0-beta.1%2Baaaaaaaa/20170521060737/WINNT_x86-msvc-x64/en-US/beta/Windows_NT%2010.0.0.0%20(x64)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.propertyVal(result.updates.update[0].$, 'type', 'minor');
				assert.isAbove(vcmp('7.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
			});
			
			it("should offer major update to 7.0 beta from 6.0 build", async function () {
				var result = await req(
					url + '/6.0-beta.202%2Bddc9989/20170521060737/WINNT_x86-msvc-x64/en-US/beta/Windows_NT%2010.0.0.0%20(x64)/update.xml'
				);
				assert.lengthOf(result.updates.update, 1);
				assert.isAbove(vcmp('7.0.0-beta.1+aaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.isBelow(vcmp('8.0.0-beta.1+aaaaaaa', result.updates.update[0].$.appVersion), 0);
				assert.propertyVal(result.updates.update[0].$, 'type', 'major');
				assert.match(result.updates.update[0].$.appVersion, /7\.0\.[\d]+-beta/);
			});
		});
	});
});
