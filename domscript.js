// zoom to a specific svg ID
var zoomToSvg = function(id) {
    var element = $('#' + id);
    if (element.length > 0) {
        var svg = $('svg');
        var bb = element[0].getBBox();
        var bbs = svg[0].getBBox();
        var bbso = svg[0].getBoundingClientRect();
        var px = bb.x + bb.width;
        var py = bb.y + bb.height / 2;
        var tx = bbs.x + bbs.width * 0.58;
        var ty = bbs.y + bbs.height * 0.18;
        var rx = px - bbs.x;
        var ry = py - bbs.y;
        var ax = tx - px;
        var ay = ty - py;
        var scx = svg.width() / bbs.width;
        var scy = svg.height() / bbs.height;
        ax *= scx;
        ay *= scy;
        rx *= scx;
        ry *= scy;
        TweenLite.to('svg', 1, {
            transformOrigin: rx + ' ' + ry,
            x: ax,
            y: ay,
            scale: 1.4
        });
    } else {
        // zoom out
        TweenLite.to('svg', 1, {
            scale: 1,
            x: 0,
            y: 0
        });
    }
}

// show svg markers for an id
var markSvg = function(id) {
    $('.marker').css({
        'display': 'none'
    });
    if (id) {
        $('#' + id).css({
            'display': 'block'
        });
    }
}

// show the description for an id
var showText = function(id) {
    // hide all descriptions
    $(".description_box").fadeOut("fast").promise().then(function() {
        // show clicked description
        $("#" + id).fadeIn("fast");
    });
}

// on first load
var initState = function() {
    // init
    showText("dfr_descr");
    zoomToSvg("dfr_touch");
    markSvg();
}

// user clicks on a circle
$('.circle, .touch').on('click', function(event) {
    var descId = $(this).data("desc-id");
    showText(descId + "_descr");
    zoomToSvg(descId + "_touch");
    markSvg(descId + "_mark");
});

// user clicks on earth svg
$('#earth').on('click', initState);

initState();