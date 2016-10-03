<?php
    require_once "svglib/svglib.php";

    // Auslesen des ODS-Files
	$z = new ZipArchive();
	if ($z->open("DFR_building.ods")) $xmls = $z->getFromName("content.xml");
	$xml = simplexml_load_string($xmls);
	list($table) = $xml->xpath("//table:table");
	$rows = $table->xpath("table:table-row");
	$rn = 0;
	foreach ($rows as $row) {
		$cn = 0;
		$cells = $row->xpath("table:table-cell");
		foreach ($cells as $cell) {
			$cellt = $cell->xpath("text:p");
			if ($cellt) list($celltext) = $cellt;
			else $celltext = "";
			if ($celltext) {
                switch ($cn) {
                    case 1:
                    case 5:
                        $varname = $celltext; continue;
                    case 2:
                    case 6:
                        if (isset($$varname)) print "WARNING. Variable $varname redefined\n";
                        $$varname = 10*floatval(str_replace(",",".",$celltext)); continue;
                }
			}
			$skip = $cell->xpath("@table:number-columns-repeated");
			if ($skip) $cn+=$skip[0];
			else $cn++;
		}
		$skip = $row->xpath("@table:number-rows-repeated");
		if ($skip) $rn+=$skip[0];
		else $rn++;
	}

    function rp($x, $y, $w, $h) { return "M$x,$y v$h h$w v-$h z ";}
    function pp($x, $y, $w, $h, $w1, $h1) { $h2 = $h-$h1; return "M$x,$y v$h1 h-$w1 v$h2 h$w v-$h z ";}

    class svgObject {
        public $id = "noname";
        public $x=0, $y=0, $w=100, $h=100, $path="", $ispath = false;
        function __construct ($id, $x, $y, $w, $h, $ww=0, $hh=0) {
            $this->id = $id;
            $this->x = $x;
            $this->y = $y;
            $this->w = $w;
            $this->h = $h;
            $this->x2 = $x+$w;
            $this->y2 = $y+$h;
            if ($ww) $this->ispath = true;
            if ($this->ispath) {
                $this->ww = $ww;
                $this->hh = $hh;
                $this->h2 = $h-$hh;
                $xx = $x + $ww;
                $this->path = "M$xx,$y v$hh h-$ww v{$this->h2} h$w v-$h z ";
            } else {
                $this->path = $this->opath = "M$x,$y v$h h$w v-$h z ";
            }
        }
        function rp() {
            return $this->path;
        }
        function addDef($sx = "path") {
            global $svg;
            if ($this->ispath) $svg->addDefs(SVGPath::getInstance($this->path, $this->id."_".$sx));
            else $svg->addDefs(SVGRect::getInstance($this->x, $this->y, $this->id."_".$sx, $this->w, $this->h));
        }
        function addBDef($sx = "path") {
            global $svg;
            $svg->addDefs(SVGPath::getInstance($this->opath.$this->ipath, $this->id."_".$sx));
        }
        function addIDef() {
            global $svg;
            $id = str_replace("bunker", "room", $this->id);
            $svg->addDefs(SVGRect::getInstance($this->ix, $this->iy, "{$id}_path", $this->iw, $this->ih));
        }
        function addRoom($st=null) {
            global $dfrplant, $st_room;
            if (!isset($st)) $st = $st_room;
            $room = SVGUse::getInstance(0, 0, $this->id, $this->id."_path", $st);
            $room->addAttribute("class","transform");
            append_svg_node($dfrplant, $room);
        }
        function addIRoom($st=null) {
            global $dfrplant, $st_room;
            if (!isset($st)) $st = $st_room;
            $id = str_replace("bunker", "room", $this->id);
            $room = SVGUse::getInstance(0, 0, $id, $id."_path", $st);
            $room->addAttribute("class","transform");
            append_svg_node($dfrplant, $room);
        }
        function addTouch($tp="path", $mp="path") {
            global $dfrplant, $st_touch, $st_mark;
            $room = SVGUse::getInstance(0,0, $this->id."_touch", $this->id."_".$tp, $st_touch);
            $room->addAttribute("class","touch");
            append_svg_node($dfrplant, $room);
            append_svg_node($dfrplant, SVGUse::getInstance(0,0, $this->id."_mark", $this->id."_".$mp, $st_mark));
        }
        function addITouch($tp="path", $mp="path") {
            global $dfrplant, $st_touch, $st_mark;
            $id = str_replace("bunker", "room", $this->id);
            $room = SVGUse::getInstance(0,0, $id."_touch", $id."_".$tp, $st_touch);
            $room->addAttribute("class","touch");
            append_svg_node($dfrplant, $room);
            append_svg_node($dfrplant, SVGUse::getInstance(0,0, $id."_mark", $id."_".$mp, $st_mark));
        }
        function addInner($th, $cth = 0) {
            if (!$cth) $cth = $th;
            $ix = $this->x+$th;
            $iy = $this->y + $cth;
            $iw = $this->w - 2*$th;
            $ih = $this->h - $cth - $th;
            $this->ix = $ix;
            $this->iy = $iy;
            $this->iw = $iw;
            $this->ih = $ih;
            $this->ipath = "M$ix,$iy v$ih h$iw v-$ih z ";
        }
    }

    function addNode($node) {
        global $dfrplant;
        append_svg_node($dfrplant, $node);
    }

    function poly(&$len, $r, $m, $x, $y) {
        $numargs = func_num_args();
        $args = func_get_args();
        $len = 0;
        if ($m[0]=='H') $p = 0; else $p = 1;
        $ret = "M$x,$y ";
        for ($i = 5; $i < $numargs; $i++) {
            if (($i+$p)%2) {
                $xn = $args[$i];
                $xr = ($xn>$x)?$r:-$r;
                $d = $xn-$xr;
                if ($i!=5) $ret.="$xr,$yr ";
                if ($i==$numargs-1) $ret.="H$xn";
                else $ret.="H$d q$xr,0 ";
                $len+=abs($x-$xn);
                $x = $xn;
            } else {
                $yn = $args[$i];
                $yr = ($yn>$y)?$r:-$r;
                $d = $yn-$yr;
                if ($i!=5) $ret.="$xr,$yr ";
                if ($i==$numargs-1) $ret.="V$yn";
                else $ret.="V$d q0,$yr ";
                $len+=abs($y-$yn);
                $y = $args[$i];
            }
            if ($i==$numargs-1 && $m[1]=='Z') $ret.=" Z";
        }
        $len-=($numargs-6)*(2-M_PI/2)*$r;
        return $ret;
    }

    function addMover($m, $mm, $col, $time) {
        return;
        $circ = SVGCircle::getInstance(0,0,2, "mover".$m, new SVGStyle(array('fill'=>$col, 'stroke' =>'none')));
        $blubb = $circ->addChild("animateMotion");
        $blubb->addAttribute("dur", $time);
        $blubb->addAttribute("repeatCount", "indefinite");
        $blubber = $blubb->addChild("mpath");
        $blubber->addAttribute("xlink:href", "#{$mm}_path", "http://www.w3.org/1999/xlink");
        addNode($circ);
    }

    // Einige Berechnungen
    $npiw = $npw-2*$npwth;
    $npih = $nph-$npwth-$npcth;
    $npppuh  = $npih - 2*$npiwth - $nptah - $npsth;
    // Viewbox
    $vbx = 0;
    $vbx2 = $btbs+$btbw;
    $vby = -$btbh;
    $vby2 = $nph;
    $vbwidth = $vbx2 - $vbx;
    $vbheight = $vby2 - $vby;

    $svg = SVGDocument::getInstance();

    // SVG styles we gonna use later
    $st_bunker = new SVGStyle(array('fill'=>'#AAA', 'fill-rule'=>'evenodd', 'opacity' => 0.9, 'stroke' =>'black'));
    $svg->addDefs(new XMLElement("<radialGradient id='room_grad' fx='0' fy='1' cx='0' cy='1' r='1'><stop offset='0' style='stop-color:#FFF'/><stop offset='1' style='stop-color:#BBB'/></radialGradient>"));
    $st_room   = new SVGStyle(array('fill'=>'url(#room_grad)', 'stroke-width' => '4px', 'stroke' =>'none', 'opacity'=>''));
    $st_touch  = new SVGStyle(array('opacity'=>'0', 'fill-rule'=>'evenodd', 'pointer-events' => 'visible'));
    $st_mark   = new SVGStyle(array('display'=>'none', 'fill'=>'none', 'stroke' =>'blue', 'stroke-width' => '4px', 'filter' => 'url(#blur)'));
    $svg->addDefs(new XMLElement("<linearGradient id='earth_grad' x1='0' y1='0' x2 = '0' y2='1'><stop offset='0' style='stop-color:#BC9E61'/><stop offset='1' style='stop-color:#6C4E11'/></linearGradient>"));
    $st_earth = new SVGStyle(array('fill'=>'url(#earth_grad)'));

    $svg->setViewbox($vbx-$vbwidth/2,$vby-$vbheight/2,2*$vbwidth,2*$vbheight);
    // $svg->setViewbox($vbx-5,$vby-5,$vbwidth+10,$vbheight+10);
    $svg->setTitle("DFR Power Plant");
    $svg->addDefs(new XMLElement("<filter id='blur'><feGaussianBlur stdDeviation='4'/></filter>"));
    $svg->addShape(SVGRect::getInstance(-$vbwidth/2,0,"earth", 2*$vbwidth, 2*$nph, $st_earth));
    $svg->addShape(SVGRect::getInstance(-$vbwidth/2,-3,"grass", 2*$vbwidth, 3, new SVGStyle(array('fill'=>'green', 'stroke' => 'none'))));
    // $svg->addShape(SVGRect::getInstance($vbx-5,0,"earth", $vbwidth+10, $nph+20, $st_earth));
    // $svg->addShape(SVGRect::getInstance($vbx-5,-3,"grass", $vbwidth+10, 3, new SVGStyle(array('fill'=>'green', 'stroke' => 'none'))));
    $svg->addShape(SVGGroup::getInstance("dfrplant"));
    $svg->addAttribute("setPreserveAspectRatio","xMidYMin slice");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title>DFR NPP</title>
    <style type="text/css">
        @font-face {
            font-family: Signika;
            src: url("fonts/signika/Signika-Regular.otf") format("opentype");
        }

        @font-face {
            font-family: Signika;
            font-weight: bold;
            src: url("fonts/signika/Signika-Bold.otf") format("opentype");
        }
        [id$=circ] {
            width: 20px;
            height: 20px;
            margin: 7px;
        }

        @media screen and (max-width : 1204px) {
            #xplant { font-size:90%; }
            [id$=circ] {
                width: 20px;
                height: 20px;
                margin: 6px;
            }
        }
        @media screen and (max-width : 860px) {
            #xplant { font-size:70%; }
            [id$=circ] {
                width: 15px;
                height: 15px;
                margin: 5px;
            }
        }
        @media screen and (max-width : 620px) {
            #xplant { font-size:50%; }
            [id$=circ] {
                width: 10px;
                height: 10px;
                margin: 4px;
            }
        }
        @media screen and (max-width : 420px) {
            #xplant { font-size:40%; }
            [id$=circ] {
                width: 5px;
                height: 5px;
                margin: 3px;
            }
        }

        body {
            font-family: Signika;
        }
        #xplant {
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        svg {
            background-color:#DEF;
            position:relative;
            width: 190%;
            margin-left: -45%;
            margin-top: -20%;
            margin-bottom: -20%;
            perspective: 1px;
        }
        #abox {
            position: absolute;
            top: 25%;
            right: 3%;
            width: 28%;
            border-radius: 25px;
            background: rgba(0,0,0,0.2);
        }
        [id$=descr], [id$=descrx] {
            position: absolute;
            color: white;
            padding: 10px;
            border-radius: 25px;
        }
        [id$=circ] {
            background: grey;
            border-radius: 50%;
            float: left;
        }
        [id$=circ].selected, [id$=circ]:hover {
            background: lightgrey;
        }
        .descr {
            clear:both;
        }
    </style>
    <script src="js/jquery.min.js"></script>
    <script src="js/greensocks/TweenMax.min.js"></script>
    <script src="js/snap.svg-min.js"></script>
    <script>
        function cid(obj) {return obj.id.split('_')[0] }
        $( function () {
            TweenLite.to("[id$=descr]", 0, {transformOrigin: '50% 50%', scale: 0});
            TweenLite.to("[id=plant_descrx]", 0, {transformOrigin: '50% 50%', scale: 1});
            TweenLite.to(".transform", 0, {transformOrigin: '50% 50%'});
            pathc = Snap("#tbc_path");
            pathh = Snap("#tbh_path");
            lenc = pathc.getTotalLength();
            lenh = pathh.getTotalLength();
            moverc = Snap("#moverc");
            moverh = Snap("#moverh");
            vel = 100;
            time = 1e6;
            function anicirc() {
            Snap.animate(0, time, function(t) {
                pos = vel*t;
                pc = pathc.getPointAtLength(pos%lenc);
                ph = pathh.getPointAtLength(pos%lenh);
                moverc.attr({ cx: pc.x, cy: pc.y });
                moverh.attr({ cx: ph.x, cy: ph.y });
            }, time*1000, mina.linear);
            }
            // anicirc();
            $(".touch, [id$=circ], [id$=descr]").mouseover(function () {
                var id = cid(this);
                $('#'+id+'_mark').css({'display': 'block'});
                TweenLite.to('#'+id+'_descr', 1, {scale: 1});
                TweenLite.to('#plant_descrx', 1, {scale: 0});
                $('#'+id+'_circ').addClass("selected");
            }).mouseout(function () {
                var id = cid(this);
                TweenLite.to('#'+id+'_descr', 1, {scale: 0, backgroundColor: 'none'});
                $('#'+id+'_mark').css({'display': 'none'});
                $('#'+id+'_circ').removeClass("selected");
                TweenLite.to(id, 0.25, {scale: 1, rotation:'0_ccw'});
                TweenLite.to('#plant_descrx', 1, {scale: 1});
                TweenLite.to('svg', 5, {scale: 1, x: 0, y: 0});
            }).mousedown(function () {
                // TweenLite.to('#'+cid(this), 0.25, {scale: 0.7, rotation:'180_ccw'});
                var id = cid(this);
                var svg = $('svg');
                // var bb = $('#'+id)[0].getBoundingClientRect();
                // var bbs = svg[0].getBoundingClientRect();
                var bb = $('#'+id)[0].getBBox();
                var bbs  = svg[0].getBBox();
                var bbso = svg[0].getBoundingClientRect();
                var px = bb.x + bb.width;
                var py = bb.y + bb.height/2;
                var tx = bbs.x + bbs.width*0.58;
                var ty = bbs.y  + bbs.height*0.18;
                var rx = px - bbs.x;
                var ry = py - bbs.y;
                var ax = tx - px;
                var ay = ty - py;
                var scx = svg.width()/bbs.width;
                var scy = svg.height()/bbs.height;
                ax*=scx; ay*=scy;
                rx*=scx; ry*=scy;
                TweenLite.to('svg', 1, {transformOrigin: rx+' '+ry, x:ax, y:ay, scale: 1.4});
                TweenLite.to('#'+id+'_descr',1, {backgroundColor: 'rgba(1,0,0,0.4)'});
            }).mouseup(function () {
                // TweenLite.to('#'+cid(this), 0.25, {scale: 1, rotation:'0_ccw'});
                // TweenLite.to('svg', 1, {scale: 1, rotation:'0_ccw'});
            });
          });
    </script>
  </head>
  <body>
  <h1 style="text-align:center">DFR Power Plant</h1>
<div id="xplant">
<?php
    // Make pathes for the building
    $npbunker = new svgObject("npbunker", 0, 0, $npw, $nph);
    $ppuroom = new svgObject("ppuroom", $npbunker->x + $npwth, $npbunker->y+$npcth, $npppuw, $npppuh);
    $storageroom = new svgObject("storageroom", $ppuroom->x, $ppuroom->y + $npppuh+$npiwth, $npstw,$npsth);
    $tankroom = new svgObject("tankroom", $storageroom->x, $storageroom->y+$npsth+$npiwth, $npiw, $nptah);
    $coreroom = new svgObject("coreroom", $tankroom->x + $npstw+$npiwth, $npcth, $npiw-$npstw-$npiwth, $npih-$npiwth-$nptah, $npppuw-$npstw, $npppuh+$npiwth);

    $ppuroom->addDef();
    $storageroom->addDef();
    $tankroom->addDef();
    $coreroom->addDef();

    $npbunker->addDef("mpath");
    $npbunker->path .= $ppuroom->path.$storageroom->path.$tankroom->path.$coreroom->path;
    $npbunker->ispath = true;
    $npbunker->addDef();
    $npbunker->addInner($npwth, $npcth);
    $npbunker->addBDef("tpath");

    $hxbunker = new svgObject("hxbunker", $npw, 0, $bhxw, $bhxh);
    $hxbunker->addInner($bhxwth);
    $hxbunker->addBDef();
    $hxbunker->addIDef();

    $tbbunker = new svgObject("tbbunker", $btbs,-$btbh, $btbw, $btbh);
    $tbbunker->addInner($btbwth);
    $tbbunker->addBDef();
    $tbbunker->addIDef();

    // Add real objects
    $dfrplant = $svg->getElementById("dfrplant");

    $ppuroom->addRoom();
    $storageroom->addRoom();
    $tankroom->addRoom();
    $coreroom->addRoom();
    $hxbunker->addIRoom();
    $tbbunker->addIRoom();

    $cx = $coreroom->x2 - $cwdis - $cdm/2;
    // $cdy = 0.4*$llh;
    // $lldy = 40;

    // Lead tube
    $lly = $coreroom->y2 - $lldy - $llh;
    $cy = $lly + $llh - $cdy;
    $blurlt_th = $ltdm/8;
    // $leadtube = SVGRect::getInstance($cx, $lly, "lt_path", $llw, $llh, null);
    // $leadtube->addAttribute('rx','20');
    // $svg->addDefs($leadtube);
    $svg->addDefs(SVGPath::getInstance(poly($lenl, 20, 'VZ', $cx, $lly+$llh/2, $lly, $cx+$llw, $lly+$llh, $cx, $lly+$llh/2), "lt_path", null));
    $svg->addDefs(new XMLElement("<filter id='lt_bl'><feGaussianBlur stdDeviation='$blurlt_th'/></filter>"));
    addNode(SVGUse::getInstance(0, 0, "lt", "lt_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $ltdm, 'stroke' =>'black'))));

    $dx = ($coreroom->ww - $npiwth)/5;
    $x1 = $ppuroom->x + $ppuroom->w - $dx;
    $x3 = $x1 - $dx;

    // decay tank tube
    $dtx = $cx + $cdm/2;
    $x5 = $x3 - $dx;
    $x4 = $x5 - $dx;
    $x6 = $dtx + $dtl - 5;
    $rhy1 = $lly + $llh - $dtdm/2;
    $rhy2 = $lly + $llh + $dtdm/2;
    $y1 = $ppuroom->y+0.7*$ppuroom->h;

    $blur_th = $dttdm/8;
    $svg->addDefs(SVGPath::getInstance(poly($lend, 3, 'VZ', $x4, $y1, $rhy2, $x6, $rhy1, $x5, $y1), "dtt_path", null));
    $svg->addDefs(new XMLElement("<filter id='dtt_bl'><feGaussianBlur stdDeviation='$blur_th'/></filter>"));
    addNode(SVGUse::getInstance(0, 0, "dtt", "dtt_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $dttdm, 'stroke' =>'#7f7500'))));
    addNode(SVGUse::getInstance(0, 0, "dtti", "dtt_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $blur_th, 'stroke' =>'white', 'filter' => 'url(#dtt_bl)'))));

    addMover('d', 'dtt', 'brown', $lend/$veld);

    // Decay tank
    $svg->addDefs(new XMLElement("<linearGradient id='dt_grad'><stop offset='0' style='stop-color:#7f7500;'/><stop offset='0.25' style='stop-color:#7f7500;'/><stop offset='0.5' style='stop-color:#d1b78c;'/><stop offset='0.75' style='stop-color:#7f7500;'/><stop offset='1' style='stop-color:#7f7500;'/></linearGradient>"));
    $lgv = new XMLElement("<linearGradient id='dt_gradv' x1='0' y1='0' x2 = '0' y2='1'/>");
    $lgv->addAttribute('xlink:href','#dt_grad', 'http://www.w3.org/1999/xlink');
    $svg->addDefs($lgv);
    $st_dt = new SVGStyle(array('fill'=>'url(#dt_gradv)', 'stroke' =>'none'));
    $svg->addDefs(SVGRect::getInstance(0, 0, "dt_path", $dttl, $dttdm, $st_dt));
    $svg->addDefs(SVGRect::getInstance(0, 0, "dtcup_path", $dttdm, $dtdm, $st_dt));
    
    $y = $lly + $llh - $dttdm/2;
    for ($i = 0; $i<3; $i++) {
        $off = ($dtdm-$dttdm)/2*sin((3-$i)*M_PI/6);
        addNode(SVGUse::getInstance($dtx, $y-$off, "dtm".$i, "dt_path", null));
        addNode(SVGUse::getInstance($dtx, $y+$off, "dtp".$i, "dt_path", null));
     }
    addNode(SVGUse::getInstance($dtx, $y, "dt0", "dt_path", null));
    addNode(SVGUse::getInstance($dtx, $lly + $llh -$dtdm/2,  "dtcupl", "dtcup_path", null));
    addNode(SVGUse::getInstance($dtx+$dttl-$dttdm, $lly + $llh -$dtdm/2,  "dtcupl", "dtcup_path", null));

    // Lead tube overlay and reflection
    addNode(SVGUse::getInstance(0, 0, "lt", "lt_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $ltdm, 'stroke' =>'black', 'stroke-opacity' => '0.4' ))));
    addNode(SVGUse::getInstance(0, 0, "dtti", "lt_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $blurlt_th, 'stroke' =>'white', 'filter' => 'url(#lt_bl)'))));


    addMover('l', 'lt', 'white', $lenl/$vell);

    // Lead pump
    $lph = 2*$ltdm;
    $lpx1 = $hxbunker->ix+10;
    $lpy = $lly+$llh-$lph/2;
    $lpw1 = 0.8*$ltdm;
    $lpx2 = $lpx1+$lpw1;
    $lpw2 = 0.4*$ltdm;
    $lpy1 = $lly+$llh-$ltdm/2;
    $lpy2 = $lly+$llh+$ltdm/2;
    $lpx3 = $lpx2+$lpw2;
    $lpy3 = $lly+$llh+$lph/2;
    $svg->addDefs(new XMLElement("<linearGradient id='lp_grad' x1='0' y1='0' x2 = '0' y2='1'><stop offset='0' style='stop-color:#00464a'/><stop offset='0.21' style='stop-color:#00464a'/><stop offset='0.53' style='stop-color:#ffffff'/><stop offset='0.91' style='stop-color:#00464a'/><stop offset='1' style='stop-color:#00464a'/></linearGradient>"));
    $st_lp = new SVGStyle(array('fill'=>'url(#lp_grad)', 'stroke' =>'none'));
    addNode(SVGPath::getInstance("M$lpx1,$lpy1 Q$lpx1,$lpy,$lpx2,$lpy v$lph Q$lpx1,$lpy3,$lpx1,$lpy2 z", "lpump1", $st_lp));
    addNode(SVGPath::getInstance("M$lpx3,$lpy1 Q$lpx3,$lpy,$lpx2,$lpy v$lph Q$lpx3,$lpy3,$lpx3,$lpy2 z", "lpump2", $st_lp));

    // the core
    $codm = 1.5*$cdm;
    $coh = 1.5*$ch;
    $x = $cx-$codm/2; $y = $cy-$coh/2;
    $cwth = $codm/20;
    $cidm = $codm-2*$cwth;
    $cih = $coh-2*$cwth;
    $clwth = $codm/25;
    $clwgx = $codm/16;
    $clwh = $cih/1.2;
    $clwgy = ($cih-$clwh)/2;
    $clwp = $cidm - 2*$clwgx - $clwth;

    $cid = $cidm-3*$cwith; $ciwh = $cih-2*$cwith;
    $st_core = new SVGStyle(array('fill'=>'#a6b900', 'fill-rule'=>'evenodd', 'opacity' => '1', 'stroke-width' => $codm/200, 'stroke' =>'white'));
    $svg->addDefs(SVGPath::getInstance("M$x,$y v$coh h$codm v-$coh z m$cwth,$cwth v$cih h$cidm v-$cih z m$clwgx,$clwgy v$clwh h$clwth v-$clwh z m$clwp,0 v$clwh h$clwth v-$clwh z", "core_path", null));
    addNode(SVGUse::getInstance(0,0, "corei", "core_path", new SVGStyle(array('fill'=>'#758600', 'fill-rule'=>'nonzero', 'stroke' =>'none'))));
    addNode(SVGUse::getInstance(0,0, "core", "core_path", $st_core));

    $svg->addDefs(new XMLElement("<linearGradient id='ct_grad'><stop offset='0' style='stop-color:#954b00;'/><stop offset='0.16' style='stop-color:#954b00;'/><stop offset='0.50' style='stop-color:#d1b78c;'/><stop offset='0.84' style='stop-color:#954b00;'/><stop offset='1' style='stop-color:#954b00;'/></linearGradient>"));
    $ct_st = new SVGStyle(array('fill'=>'url(#ct_grad)', 'stroke'=>'none'));
    $svg->addDefs(SVGRect::getInstance(0, 0, "ct_path", $ctdm, $ch-$ctdm, $ct_st));
    $nct = 7;
    $pitch = $cdm/$nct;
    $x = $cx-$cdm/2;
    $y = $cy-$ch/2+$ctdm/2;
    for ($i = 0; $i < $nct; $i++) addNode(SVGUse::getInstance($x+$i*$pitch, $y, "ct".$i, "ct_path", null));

    // Fuel tube
    $y2 = $cy+($ch-$ctdm)/2;
    $y3 = $cy-($ch-$ctdm)/2;
    $x2 = $cx+($cdm-$ctdm)/2;
    $blur_th = $ctdm/8;
    $svg->addDefs(SVGPath::getInstance(poly($lenf, 3, 'VZ', $x3, $y1, $y2, $x2, $y3, $x1, $y1), "ft_path", null));
    $svg->addDefs(new XMLElement("<filter id='ft_bl'><feGaussianBlur stdDeviation='$blur_th'/></filter>"));
    addNode(SVGUse::getInstance(0, 0, "ft", "ft_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $ctdm, 'stroke' =>'#954b00'))));
    addNode(SVGUse::getInstance(0, 0, "fti", "ft_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $blur_th, 'stroke' =>'white', 'filter' => 'url(#ft_bl)'))));

    addMover('f', 'ft', 'yellow', $lenf/$velf);

    // Fuel drain
    $tankdy = $tankroom->y+$tankdt;
    addNode(SVGRect::getInstance($x3-$ctdm/2+10, $y2+$ctdm/2, "fdt", $ctdm, $tankdy-($y2+$ctdm/2), $ct_st));

    // Fuel melting fuse
    $mfdm = 8;
    $mfh = 13;
    $svg->addDefs(new XMLElement("<linearGradient id='mf_grad'><stop offset='0' style='stop-color:#7cbc00'/><stop offset='0.2' style='stop-color:#7cbc00'/><stop offset='0.5' style='stop-color:#d1f4bc'/><stop offset='0.8' style='stop-color:#7cbc00'/><stop offset='1' style='stop-color:#7cbc00'/></linearGradient>"));
    $fmf = SVGRect::getInstance(0, 0, "mf", $mfdm, $mfh, new SVGStyle(array('fill'=>'url(#mf_grad)', 'stroke'=>'none')));
    $fmf->addAttribute('rx','0.5');
    $svg->addDefs($fmf);
    addNode(SVGUse::getInstance($x3-$mfdm/2+10, $y2+$ctdm/2+4, "fmf", "mf"));

    // Decay tank drain
    addNode(SVGRect::getInstance($x4-$dttdm/2+40, $rhy2+$dttdm/2, "dtdt", $dttdm, $tankdy-($rhy2+$dttdm/2), new SVGStyle(array('fill'=>'url(#dt_grad)', 'stroke' =>'none'))));
    // Decay tank melting fuse
    addNode(SVGUse::getInstance($x4-$mfdm/2+40, $rhy2+$dttdm/2+4, "dtmf", "mf"));

    // Decay tanks
    $tankswd = ($tankroom->w-$tanksw)/2;
    $tanksx = $tankroom->x + $tankswd;
    $tanksx2 = $tankroom->x2 - $tankswd;
    $tanksdly = $tankdy + $tanksdh;
    $svg->addDefs(SVGRect::getInstance(0,0,"tts_path", $dttdm, $tanksdh, new SVGStyle(array('stroke' =>'none', 'fill' => 'url(#dt_grad)'))));
    $svg->addDefs(new XMLElement("<linearGradient id='dtb_grad'><stop offset='0' style='stop-color:#954000'/><stop offset='0.2' style='stop-color:#446688'/><stop offset='0.5' style='stop-color:#ffffff'/><stop offset='0.8' style='stop-color:#446688'/><stop offset='1' style='stop-color:#954000'/></linearGradient>"));
    $svg->addDefs(SVGRect::getInstance(0,0,"ttb_path", $tankdm, $tankh, new SVGStyle(array('stroke' =>'none', 'fill' => 'url(#dtb_grad)'))));
    $i=0;
    for ($x = $tanksx; $x <= ($tanksx + $tanksw); $x+=$tankw) {
        if ($x!=$tanksx && $x!=($tanksx + $tanksw)) addNode(SVGUse::getInstance($x-$dttdm/2, $tankdy, "tts", "tts_path", none));
        addNode(SVGUse::getInstance($x-$tankdm/2, $tanksdly, "ttb".$i, "ttb_path", none));
        $i++;
    }
    $blur_th = $dttdm/4;
    $svg->addDefs(SVGPath::getInstance(poly($len, 10, 'V', $tanksx, $tanksdly, $tankdy, $tanksx2, $tanksdly), "dtd_path", none));
    addNode(SVGUse::getInstance(0,0,"dtd","dtd_path",new SVGStyle(array('stroke' =>'#7f7500', 'stroke-width' => $dttdm, 'fill' => 'none'))));
    addNode(SVGUse::getInstance(0, 0, "dtdi", "dtd_path", new SVGStyle(array('fill'=>'none', 'stroke-width' => $blur_th, 'stroke' =>'white', 'filter' => 'url(#dtt_bl)'))));

    $hxx = $cx + $llw;

    // Turbine loop
    $tlbot = $lly+$llh+$tldy;
    $tlx2 = $hxx + $tldx;

    $tbh = $tbbunker->y+$tbbunker->h/2;
    $tbmx = $tbx + $tbw/2;
    $tbx2 = $tbbunker->x2 - $tlwgap;
    $tby1 = $tbh + $tldy2;

    $blur_th = $tdm/5;
    $svg->addDefs(new XMLElement("<filter id='tl_bl'><feGaussianBlur stdDeviation='$blur_th'/></filter>"));
    $svg->addDefs(SVGPath::getInstance(poly($lenc, 10, 'H', $tbmx, $tbh, $tbx2, $tby1, $tlx2, $tlbot, $hxx, $cy), "tbc_path", null));
    $svg->addDefs(SVGPath::getInstance(poly($lenh, 20, 'V',$hxx,$cy,$tbh,$tbmx), "tbh_path", null));
    addNode(SVGUse::getInstance(0,0,"tbc", "tbc_path", new SVGStyle(array('fill'=>'none', 'stroke' =>'navy', 'stroke-width' => $tdm, 'stroke-opacity' => '0.75'))));
    addNode(SVGUse::getInstance(0,0,"tbc_b", "tbc_path", new SVGStyle(array('fill'=>'none', 'filter' => 'url(#tl_bl)', 'stroke' =>'white', 'stroke-width' => $tdm/4, 'stroke-opacity' => '1'))));
    addNode(SVGUse::getInstance(0,0,"tbh", "tbh_path", new SVGStyle(array('fill'=>'none', 'stroke' =>'orange', 'stroke-width' => $tdm, 'stroke-opacity' => '0.75'))));
    addNode(SVGUse::getInstance(0,0,"tbh_b", "tbh_path", new SVGStyle(array('fill'=>'none', 'filter' => 'url(#tl_bl)', 'stroke' =>'white', 'stroke-width' => $tdm/4, 'stroke-opacity' => '1'))));
    
    addMover('c', 'tbc', 'lightblue', $lenc/$velt);
    addMover('h', 'tbh', 'red', $lenh/$velt);

    // Turbine
    addNode(SVGRect::getInstance($tbx, $tbh-$tbdm/2, "tb", $tbw, $tbdm, new SVGStyle(array('fill'=>'url(#lp_grad)', 'stroke' =>'none', 'opacity'=>'1'))));

    // Heat exchanger
    $hxy = $hxbunker->y + $hxbunker->h/2;
    $hxdm2 = $hxdm/2;
    $hxx0 = $hxx-$hxdm2;
    $hxy0 = $cy-$hxh/2;
    $hxch = 0.2*$hxh;
    $svg->addDefs(new XMLElement("<linearGradient id='hx_grad'><stop offset='0' style='stop-color:#954088'/><stop offset='0.2' style='stop-color:#a25897'/><stop offset='0.5' style='stop-color:#ffffff'/><stop offset='0.8' style='stop-color:#a25897'/><stop offset='1' style='stop-color:#954088'/></linearGradient>"));
    addNode(SVGRect::getInstance($hxx0, $hxy0, "hx", $hxdm, $hxh, new SVGStyle(array('fill'=>'url(#hx_grad)', 'opacity' => '1', 'stroke-width' => '1px', 'stroke' =>'none'))));
    
    $svg->addDefs(new XMLElement("<radialGradient id='hxcap_grad' cx='0.5' cy='0.25' fx='0.5' fy='0.2' r='1'><stop offset='0' style='stop-color:#ffffff'/><stop offset='0.7' style='stop-color:#954088'/><stop offset='1' style='stop-color:#954088'/></radialGradient>"));
    $svg->addDefs(SVGPath::getInstance("M$hxdm,0 q0,-$hxch,-$hxdm2,-$hxch Q0,-$hxch,0,0 z", "hxc_path", new SVGStyle(array('fill'=>'url(#hxcap_grad)', 'stroke-width' => '1px', 'stroke' =>'none'))));
    addNode(SVGUse::getInstance($hxx0, $hxy0, "hxct", "hxc_path", null));
    $hxcb = SVGUse::getInstance($hxx0, -($hxy0+$hxh), "hxcb", "hxc_path", null);
    $hxcb->addAttribute('transform','scale(1,-1)');
    addNode($hxcb);

    // PPU
    $ppuhu = 0.4*$ppuh;
    $x = $ppuroom->x + 0.99*$ppuroom->w - $ppuw;
    $y = $ppuroom->y  + 0.96*$ppuroom->h - $ppuhu;

    $svg->addDefs(new XMLElement("<linearGradient id='ppu_grad' x1='0' y1='0' x2 = '0' y2='1'><stop offset='0' style='stop-color:#000076;stop-opacity:0'/><stop offset='1' style='stop-color:#000076'/></linearGradient>"));
    addNode(SVGRect::getInstance($x, $y, "ppu", $ppuw, $ppuhu, new SVGStyle(array('fill'=>'url(#ppu_grad)', 'opacity' => '0.8', 'stroke-width' => '0.5', 'stroke' =>'black'))));
    $svg->addDefs(new XMLElement("<linearGradient id='pput_grad'><stop offset='0' style='stop-color:#000000'/><stop offset='0.22' style='stop-color:#000070'/><stop offset='0.5' style='stop-color:#9fa89f'/><stop offset='0.78' style='stop-color:#000070'/><stop offset='1' style='stop-color:#000000'/></linearGradient>"));
    $pputm = $ppuh- $ppuhu;
    $yu = $y-$pputm;
    $pputx = array(0.10, 0.20, 0.30, 0.50, 0.70);
    $pputw = array(0.05, 0.08, 0.06, 0.05, 0.09);
    $pputh = array(0.90, 1.00, 0.30, 0.40, 0.60);
    for ($i = 0; $i < sizeof($pputx); $i++)
    addNode(SVGRect::getInstance($x+$pputx[$i]*$ppuw, $yu+(1-$pputh[$i])*$pputm, "pput1", $pputw[$i]*$ppuw, $pputh[$i]*$pputm, new SVGStyle(array('fill'=>'url(#pput_grad)', 'opacity' => '0.8', 'stroke' =>'none'))));

    // Storage shelfs
    $shtw = 0.9*$storageroom->w;
    $shth = 0.7*$storageroom->h;
    $shxd = ($storageroom->w-$shtw)/2;
    $shyd = 0.2*$storageroom->h;
    $shx0 = $storageroom->x + $shxd;
    $shy0 = $storageroom->h + $shyd;
    $shxp = 0.1*$shtw;
    $shw = 0.08*$shtw;
    $shyp = 0.2*$shth;
    $shh = 0.05*$shth;
    $shx00 = $shx0+($shxp-$shw)/2;
    $shy00 = $shy0+($shyp-$shh)/2;
    for ($x = 0; $x < $shtw; $x+=$shxp)
      for ($y = 0; $y < $shth; $y+=$shyp)
    addNode(SVGRect::getInstance($shx00+$x, $shy00+$y, null, $shw, $shh, new  SVGStyle(array('fill'=>'DarkSlateGray', 'stroke'=>'black', 'stroke-width' => '0.1'))));

    // Storage crane
    $crrx0 = 10;
    $crrx1 = 90;
    $crx0 = $ppuroom->x+$crrx0;
    $crx1 = $ppuroom->x+$crrx1;
    $cry = $ppuroom->y -1;
    $crw = 25;
    $crh = 5;
    $svg->addDefs(new XMLElement("<linearGradient id='crane_grad' x1='0' y1='0' x2 = '0' y2='1'><stop offset='0' style='stop-color:white'/><stop offset='1' style='stop-color:grey'/></linearGradient>"));
    $crane = SVGRect::getInstance($crx0, $cry, "crane", $crw, $crh, new  SVGStyle(array('fill'=>'url(#crane_grad)', 'stroke'=>'black', 'stroke-width' => '0.2')));

/*
    $blubb = $crane->addChild("animate");
    $blubb->addAttribute("dur", "10s");
    $blubb->addAttribute("repeatCount", "indefinite");
    $blubb->addAttribute("values", "$crx0; $crx1; $crx0");
    $blubb->addAttribute("keyTimes", "0; 0.5; 1");
    $blubb->addAttribute("attributeName", "x");
    $blubb->addAttribute("attributeType", "XML");
    $blubb->addAttribute("keySplines", ".42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1;");
*/
    addNode($crane);

    $caw = 3;
    $cah = 80;
    $cax0 = $crx0 + $crw/2 - $caw/2;
    $cax1 = $crx1 + $crw/2 - $caw/2;
    $cay = $cry + $crh;
    $svg->addDefs(new XMLElement("<linearGradient id='arm_grad'><stop offset='0' style='stop-color:grey'/><stop offset='0.5' style='stop-color:white'/><stop offset='1' style='stop-color:grey'/></linearGradient>"));
    $arm = SVGRect::getInstance($cax0, $cay, "arm", $caw, $cah, new  SVGStyle(array('fill'=>'url(#arm_grad)', 'stroke'=>'black', 'stroke-width' => '0.2')));
/*
    $blubb = $arm->addChild("animate");
    $blubb->addAttribute("dur", "10s");
    $blubb->addAttribute("repeatCount", "indefinite");
    $blubb->addAttribute("values", "$cax0; $cax1; $cax0");
    $blubb->addAttribute("keyTimes", "0; 0.5; 1");
    $blubb->addAttribute("attributeName", "x");
    $blubb->addAttribute("attributeType", "XML");
    $blubb->addAttribute("keySplines", ".42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1;");
    $blubb = $arm->addChild("animate");
    $blubb->addAttribute("dur", "10s");
    $blubb->addAttribute("repeatCount", "indefinite");
    $blubb->addAttribute("values", "80; 15; 80");
    $blubb->addAttribute("keyTimes", "0; 0.5; 1");
    $blubb->addAttribute("attributeName", "height");
    $blubb->addAttribute("attributeType", "XML");
    $blubb->addAttribute("keySplines", ".42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1; .42 0 1 1; 0 0 .59 1;");
*/
    addNode($arm);
                
    // All the bunkers
    $npbunker->addRoom($st_bunker);
    $hxbunker->addRoom($st_bunker);
    $tbbunker->addRoom($st_bunker);

    // All touch areas
    $npbunker->addTouch("tpath","mpath");
    $ppuroom->addTouch();
    $storageroom->addTouch();
    $tankroom->addTouch();
    $coreroom->addTouch();
    $hxbunker->addITouch();
    $tbbunker->addITouch();

    print($svg->asxML(null, false));
?>
    <div id="abox">
     <div id="tbroom_circ"></div>
     <div id="hxroom_circ"></div>
     <div id="ppuroom_circ"></div>
     <div id="storageroom_circ"></div>
     <div id="tankroom_circ"></div>
     <div id="coreroom_circ"></div>
     <div id="npbunker_circ"></div>
     <br/>
     <div class="descr">
     <div id="plant_descrx">
     <h2>DFR Power Plant</h2>
     <h3>This is a fully-fledged 1.5 GW electrical power plant</h3>
In the core the heat is transfered to the coolant (black loop),
preferably pure Lead, which in turn transfers the heat to the conventional
part where the preferred medium is supercritical CO₂ or water, driving
the turbines (top). The nuclear part is so small that it can easily be built
subterraneously. A high operating temperature of about 1000 °C ensures
cheap hydrogen production for the chemical industry, automotive fuel
synthesis, and water desalination. The DFR's high transmutation capability
enables not only the incineration of its own long-lived remnants but also
the treatment of the nuclear waste produced by today's reactors. Due to a
strong negative temperature coefficient, the power plant can be controlled
just by the output power demand - no control rods are necessary. High
flexibility in the electricity production makes the DFR ideal for combination
with other power plants in a complex electrical grid. Estimated overnight
capital costs are below 1 €/W and electricity production costs are about
6 €/MWh - a factor five lower than today (see bottom of the poster).
     </div>
     <div id="tbroom_descr">
     <h2>Turbine room</h2>
     <h3>Here are the turbines</h3>
      Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
     </div>
     <div id="hxroom_descr">
     <h2>Heat exchanger room</h2>
     <h3>Here is the heat exchanger</h3>
      Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
     </div>
     <div id="ppuroom_descr">
     <h2>PPU room</h2>
     <h3>The Pyroprocessing Unit (PPU)</h3>
As the fuel is undiluted and the processing is based on pure chloride salts, reprocessing simplifies
remarkably. The PPU's major separation process for both, DFR/s and DFR/m, utilizes fractional
distillation/rectification, a standard procedure in industrial chemistry. For the DFR/m, a further
chlorination and reduction process needs to be inserted before and after, respectively. Noble
gas purging removes Iodine and precipitates the noble metals.
     </div>
     <div id="storageroom_descr">
     <h2>Storage room</h2>
     <h3>Here are the fission products</h3>
Although the fission products are removed from the core, they need to be cooled, depending on their
activity. While processed, the short-lived fission products (half-lives from hours to several days) decay
inside the PPU (2). Isotopes with half-lives up to one year need to be actively cooled and are stored
in a tubular system (3b) inside the primary coolant, generating heat power of up to 20 MW.
The very long-lived ones, like Tc-99, can be transmuted in the core (1). All other fission products,
put into metal capsules, decay inside a passively air-cooled storage room (3a) in a few centuries,
roughly 90% of them within 100 years.
     </div>
     <div id="tankroom_descr">
     <h2>Tank room</h2>
     <h3>Here are the subcritical tanks</h3>
In case of desired fuel removal due to maintenance or in case of
overheating, the liquid salt from the PPU, from the core, or from the
actively cooled decay tank can be drained via their respective fuse
plugs (green) where it is normally frozen. These plugs open passively
if overheating and/or power outage occurs, the salt melts and flows
into tanks by gravity inside a large subcritical heat storage.
The decay heat lowers from 100 MW to some 5 MW within 2 weeks
and dissipates passively. The room is filled with material with good
heat conductivity and high volumetric heat capacity, e.g. iron bricks.
     </div>
     <div id="coreroom_descr">
     <h2>Reactor room</h2>
     <h3>Here is the DFR core</h3>
In the core the fuel is distributed over 10,000 vertical tubes and becomes critical. These tubes are
surrounded by the coolant (liquid lead) removing the heat, contrary to the molten-salt reactor (MSR)
concepts where the heat is removed by the fuel itself.

<p><b>DFR/s</b> is already quite different from the "usual" MSR
concepts. Thanks to the Dual Fluid principle, heat can be
removed from the core much more efficiently, making
it possible to use pure undiluted actinide chloride salts.
This makes the core very compact, which in turn enables
the exploitation of expensive, highly corrosion resistant
materials at 1000 °C.</p>

<p><b>DFR/m</b> further increases the power density and further
hardens the neutron spectrum. Due to much better heat
conduction of the metallic fuel, several improvements of the reactor construction could be additionally
made considerably enhancing the economy. First simulations indicate conversion ratios close to 2 (most
actinides with even number of neutrons become burnable).</p>
     </div>
     <div id="npbunker_descr">
     <h2>Reactor bunker</h2>
     <h3>Here is all the nuclear part</h3>
      Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
     </div>
    </div>
</div>
  </body>
</html>
