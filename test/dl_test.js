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

describe("Downloads", function () {
	var req = function (uri) {
		return rp({
			uri,
			simple: false,
			resolveWithFullResponse: true,
			followRedirect: false
		})
		.then(function (res) {
			if (res.statusCode !== 200 && res.statusCode !== 302) {
				console.log(res.body);
			}
			assert.include([200, 302], res.statusCode);
			return res.headers.location;
		});
	};
	
	describe("release channel", function () {
		it("should offer 6.0 for Mac by default", async function () {
			var result = await req(
				url + '?channel=release&platform=mac'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6.0\.[\d]+\.dmg$/);
		});
		
		it("should offer 6.0 for Mac with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=mac&from=4.0'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6.0\.[\d]+\.dmg$/);
		});
		
		it("should offer 6.0 for Windows by default", async function () {
			var result = await req(
				url + '?channel=release&platform=win32'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6.0\.[\d]+_setup.exe$/);
		});
		
		it("should offer 6.0 for Windows with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=win32&from=4.0'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6.0\.[\d]+_setup.exe$/);
		});
		
		it("should offer 6.0 for Linux x86_64 by default", async function () {
			var result = await req(
				url + '?channel=release&platform=linux-x86_64'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6\.0\.[\d]+_linux-x86_64.tar.bz2$/);
		});
		
		it("should offer 6.0 for Linux x86_64 with from=4.0", async function () {
			var result = await req(
				url + '?channel=release&platform=linux-x86_64&from=4.0'
			);
			assert.match(result, /client\/release\/6\.0\.[\d]+\/Zotero-6\.0\.[\d]+_linux-x86_64.tar.bz2$/);
		});
		
		it("should offer setup.exe for win32 5.0.77", async function () {
			var result = await req(
				url + '?channel=release&platform=win32&version=5.0.77'
			);
			assert.match(result, /client\/release\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_setup.exe$/);
		});
	});
	
	describe("beta channel", function () {
		describe("Windows 64-bit", function () {
			it("should offer 7.0 beta installer by default", async function () {
				var result = await req(
					url + '?channel=beta&platform=win-x64'
				);
				assert.match(result, /client\/beta\/7\.0\.[\d]+-beta.+Zotero-7\.0\.[\d]+-beta\.[^/]+_x64_setup.exe/);
			});
		});
		
		describe("Windows 32-bit", function () {
			it("should offer 7.0 beta installer by default", async function () {
				var result = await req(
					url + '?channel=beta&platform=win32'
				);
				assert.match(result, /client\/beta\/7\.0\.[\d]+-beta.+Zotero-7\.0\.[\d]+-beta\.[^/]+_win32_setup.exe/);
			});
			
			it("should offer setup.exe for 5.0.77", async function () {
				var result = await req(
					url + '?channel=beta&platform=win32&version=5.0.77'
				);
				assert.match(result, /client\/beta\/5\.0\.[\d]+\/Zotero-5\.0\.[\d]+_setup.exe$/);
			});
		});
	});
	
	describe("dev channel", function () {
		describe("Windows 64-bit", function () {
			it("should offer 7.0 ZIP", async function () {
				var result = await req(
					url + '?channel=dev&platform=win-x64-zip'
				);
				assert.match(result, /client\/dev\/7\.0\.[\d]+-dev\.[^/]+\/Zotero-7\.0\.[\d]+-dev\.[^/]+_win-x64\.zip/);
			});
		});
		
		describe("Windows 32-bit", function () {
			it("should offer 7.0 dev installer by default", async function () {
				var result = await req(
					url + '?channel=dev&platform=win32'
				);
				assert.match(result, /client\/dev\/7\.0\.[\d]+-dev\.[^/]+\/Zotero-7\.0\.[\d]+-dev\.[^/]+_win32_setup\.exe/);
			});
		});
	});
});
