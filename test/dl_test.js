const Promise = require('bluebird');
const rp = require('request-promise');
const url = 'http://localhost:12562/dl.php';
const assert = require('chai').assert;
// Doesn't work properly as of 6/2017: cmp('5.0-beta.202+ddc9989', '5.0-beta.208+0495f2920') == -1
//const vcmp = require('mozilla-version-comparator');
const vcmp = function (a, b) {
	if (a > b) return -1;
	if (b > a) return 1;
	return 0;
}

describe("dl.php", function () {
	var req = function (uri) {
		return rp({
			uri,
			simple: false,
			resolveWithFullResponse: true,
			followRedirect: false
		})
		.then(function (res) {
			//console.log(res);
			return res.headers.location;
		});
	};
	
	describe("release channel", function () {
		it("should offer 5.0 for Mac by default", async function () {
			var result = await req(
				url + '?channel=release&platform=mac'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5.0\.[\d]+\.dmg$/);
		});
		
		it("should offer 5.0 for Mac with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=mac&from=4.0'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5.0\.[\d]+\.dmg$/);
		});
		
		it("should offer 5.0 for Windows by default", async function () {
			var result = await req(
				url + '?channel=release&platform=win32'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5.0\.[\d]+_setup.exe$/);
		});
		
		it("should offer 5.0 for Windows with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=win32&from=4.0'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5.0\.[\d]+_setup.exe$/);
		});
		
		it("should offer 5.0 for Linux x86_64 by default", async function () {
			var result = await req(
				url + '?channel=release&platform=linux-x86_64'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_linux-x86_64.tar.bz2$/);
		});
		
		it("should offer 5.0 for Linux x86_64 with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=linux-x86_64&from=4.0'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_linux-x86_64.tar.bz2$/);
		});
		
		it("should offer setup.exe for win32 5.0.76", async function () {
			var result = await req(
				url + '?channel=release&platform=win32&version=5.0.76'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_setup.exe$/);
		});
		
		it("should offer setup32.exe for win32 5.0.77", async function () {
			var result = await req(
				url + '?channel=release&platform=win32&version=5.0.77'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_setup32.exe$/);
		});
		
		it.skip("should offer setup.exe for win64", async function () {
			var result = await req(
				url + '?channel=release&platform=win64'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_setup.exe$/);
		});
	});
	
	describe("beta channel", function () {
		it("should offer 5.0 Beta for Windows", async function () {
			var result = await req(
				url + '?channel=beta&platform=win32'
			);
			assert.ok(result.match(/client\/beta\/5\.0\.[\d]+-beta.+Zotero-5\.0\.[\d]+-beta/));
		});
	});
});
