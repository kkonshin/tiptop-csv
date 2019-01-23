var page = require('webpage').create(),
    url = 'https://www.wildberries.ru/catalog/5544135/detail.aspx?targetUrl=ES', // Change this to the URL you want to request
    response;

page.onResourceReceived = function (r) {
    response = r;
};

phantom.onError = function(msg, trace) {

    console.log(msg);
    console.log(trace);
    phantom.exit(1);
};

page.open (url, 'GET', '', function (status) {
    body = page.evaluate(function(){
        return document.body.innerHTML;
    });
    console.log(status);
    console.log(body);
    // console.log(JSON.stringify(response));
    phantom.exit();
});