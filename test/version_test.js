const rp = require('request-promise');
const url = 'http://localhost:12562/version.php';
const assert = require('chai').assert;

describe("Version", function () {
	it("should return the latest version as plain text", async function () {
		var res = await rp({
			uri: url + '?channel=release&platform=mac',
			resolveWithFullResponse: true
		});
		assert.equal(res.statusCode, 200);
		assert.match(res.headers['content-type'], /^text\/plain/);
		assert.match(res.body, /^8\.0(\.[\d]+)?$/);
	});

	it("should return all platforms as JSON when no platform given", async function () {
		var res = await rp({
			uri: url + '?channel=release',
			resolveWithFullResponse: true
		});
		assert.equal(res.statusCode, 200);
		assert.match(res.headers['content-type'], /^application\/json/);
		var body = JSON.parse(res.body);
		assert.match(body.mac, /^8\.0(\.[\d]+)?$/);
		assert.match(body['win-x64'], /^8\.0(\.[\d]+)?$/);
		assert.match(body['linux-x86_64'], /^8\.0(\.[\d]+)?$/);
	});
});
