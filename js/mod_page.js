class Point {
    constructor(x, y) {
        this.x = x;
        this.y = y;
    }

    toScaled(scale) {
        return new Point(Math.round(this.x * scale),
                         Math.round(this.y * scale));
    }

    fromScaled(scale) {
        return new Point(Math.round(this.x / scale),
                         Math.round(this.y / scale));
    }
}


class Rect {
    constructor(p1, p2) {
        this.p1 = p1;
        this.p2 = p2;
    }

    left() {
        return (this.p1.x < this.p2.x) ? this.p1.x : this.p2.x;
    }

    top() {
        return (this.p1.y < this.p2.y) ? this.p1.y : this.p2.y;
    }

    width() {
        return Math.abs(this.p1.x - this.p2.x);
    }

    height() {
        return Math.abs(this.p1.y - this.p2.y);
    }

    right() {
        return this.left() + this.width();
    }

    bottom() {
        return this.top() + this.height();
    }

    topLeft() {
        return new Point(this.left(), this.top());
    }

    bottomRight() {
        return new Point(this.right(), this.bottom());
    }

    inEntry(point) {
        if (point.x < this.left() || point.x > this.right())
            return false;

        if (point.y < this.top() || point.y > this.bottom())
            return false;

        return true;
    }

    toScaled(scale) {
        return new Rect(this.p1.toScaled(scale), this.p2.toScaled(scale));
    }

    fromScaled(scale) {
        return new Rect(this.p1.fromScaled(scale), this.p2.fromScaled(scale));
    }

    serialize() {
        return {'left': this.left(),
                'top': this.top(),
                'width': this.width(),
                'height': this.height()};
    }
}


class GraphicObject {
    constructor(editor, type) {
        this.editor = editor;
        this.type = type;
        this.div = document.createElement("div");
        this.div.style.display = "none";
        editor.mainDiv.insertBefore(this.div, editor.schImg);
    }

    setOnClick(cb) {
        this.div.onclick = cb;
    }

    setOnDblClick(cb) {
        this.div.ondblclick = cb;
    }

    setOnMouseMove(cb) {
        this.div.onmousemove = cb;
    }

    setBorder(border) {
        this.div.style.border = border;
    }

    destroy() {
        this.div.remove();
    }
}

class GraphicObjectRect extends GraphicObject {
    constructor(editor, rect) {
        super(editor, 'rect');
        this.setRect(rect)

        this.div.style.border = "solid 3px blue";
        this.div.style.position = "absolute";
        this.div.style.display = "block";
    }

    setRect(rect) {
        this.rect = rect;
        var s = this.editor.scale;
        this.div.style.left = this.editor.left() + rect.toScaled(s).left() - 3;
        this.div.style.top = this.editor.top() + rect.toScaled(s).top() - 3;
        this.div.style.width = rect.toScaled(s).width() - 3;
        this.div.style.height = rect.toScaled(s).height() - 3;
    }

    update() {
        this.setRect(this.rect);
    }

    setName(name) {
        this.div.title = name;
    }
}


class GraphicObjectPoint extends GraphicObject {
    constructor(editor, point) {
        super(editor, 'point');
        this.setPoint(point);

        this.div.style.border = "solid 3px blue";
        this.div.style.position = "absolute";
        this.div.style.display = "block";
    }

    setPoint(point) {
        this.point = point;
        var s = this.editor.scale;
        this.div.style.left = this.editor.left() + point.toScaled(s).x - 3;
        this.div.style.top = this.editor.top() + point.toScaled(s).y - 3;
        this.div.style.width = 0;
        this.div.style.height = 0;
    }

    update() {
        this.setRect(this.rect);
    }
}


class Area {
    constructor(editor, graphRect, name) {
        this.editor = editor;
        this.graphRect = graphRect;
        this.selected = false;
        this.setName(name);

        var f = function () { this.onClick(); };
        this.graphRect.setOnClick(f.bind(this));
        f = function () { this.onDblClick(); };
        this.graphRect.setOnDblClick(f.bind(this));
    }

    setName(name) {
        this.name = name;
        this.graphRect.setName(name);
    }

    onClick() {
        this.editor.selectArea(this);
    }

    onDblClick() {
        var idxInfo = NaN;

        if (idxInfo) {
            var [startIdxOffset, idxStep] = idxInfo;
            this.editor.indexLineSet(startIdxOffset, idxStep);
            this.editor.indexLineShow();
        }


        var p = this.graphRect.rect.bottomRight();
        this.editor.editBoxShow(p.toScaled(this.editor.scale),
                                function (name) {},
                                function () {},
                                this.name);
    }

    select() {
        this.graphRect.setBorder("solid 3px red");
        this.selected = true;
    }

    unselect() {
        this.graphRect.setBorder("solid 3px blue");
        this.selected = false;
    }

    rect() {
        return this.graphRect.rect;
    }

    destroy() {
        this.graphRect.destroy();
    }

    serialize() {
        if (this.indexLine)
            return NaN;
        return {'name': this.name,
                'rect': this.graphRect.rect.serialize()};
    }
}

class IndexLine {
    constructor(editor) {
        this.editor = editor;
        this.offset = NaN;
        this.step = NaN;
        this.grid = [];
    }

    makeByArea(area) {
        var rect = area.graphRect.rect;
        var m = area.name.match(/\^(\d+) (\d+)/);
        if (m.length < 3)
            return NaN;

        var areaStart = parseInt(m[1]);
        var areaEnd = parseInt(m[2]);
        var areaLeft = rect.left();
        var areaRight = rect.right();

        this.step = (areaRight - areaLeft) / (areaEnd - areaStart);
        this.offset = areaLeft - (areaStart - this.editor.startIdx) * this.step;
        return true;
    }

    setParams(offset, step) {
        this.offset = offset;
        this.step = step;
    }

    show() {
        if (!this.step)
            return;

        var startIdx = this.editor.startIdx;
        var endIdx = this.editor.endIdx;

        var cnt = 0
        for (var i = startIdx; i <= endIdx; i++) {
            var p1 = new Point(this.offset + cnt * this.step - 1, 50);
            var p2 = new Point(this.offset + cnt * this.step + 1, this.editor.origHeight - 50);
            var o = this.editor.addRect(new Rect(p1, p2));
            o.setBorder("solid 1px green");
            this.grid.push(o);
            cnt ++;
        }
    }

    isShow() {
        return this.grid.length ? true : false;
    }

    hide() {
        if (!this.grid.length)
            return;
        for (var i in this.grid) {
            var o = this.grid[i];
            this.editor.remove(o);
        }
        this.grid = [];
    }

    serialize() {
        if (!this.step)
            return NaN;
        return {'offset': this.offset,
                'step': this.step};
    }

    destroy() {
        this.hide();
        this.offset = NaN;
        this.step = NaN;
    }
}


class SchematicPage {
    constructor(id, w, h, startIdx, endIdx, offset, step, mainDiv, schImg) {
        this.id = id;
        this.schImg = schImg;
        this.origWidth = w;
        this.origHeight = h;

        this.startIdx = startIdx;
        this.endIdx = endIdx;

        this.defWidth = this.width();
        this.defHeight = this.height();
        this.setWidth(this.defWidth);

        this.mainDiv = mainDiv;
        this.areas = [];
        this.GraphicObjects = [];
        this.mousePos = new Point(0,0);

        this.indexLine = new IndexLine(this);
        if (offset && step)
            this.indexLine.setParams(offset, step);
    }

    left() {
        return this.schImg.offsetLeft;
    }

    top() {
        return this.schImg.offsetTop;
    }

    onMouseMove(p) {
        this.mousePos = p;
    }

    addArea(rect, name) {
        var graphRect = this.addRect(rect);
        var area = new Area(this, graphRect, name);
        this.areas.push(area);
        return area;
    }

    removeArea(area) {
        for (var i in this.areas) {
            if (this.areas[i] === area) {
                this.areas.splice(i, 1);
            }
        }
        area.destroy();
    }

    addPoint(p) {
        var o = new GraphicObjectPoint(this, p);
        this.GraphicObjects.push(o);
        return o;
    }

    addRect(rect) {
        var o = new GraphicObjectRect(this, rect);
        this.GraphicObjects.push(o);
        return o;
    }

    remove(o) {
        for (var i in this.GraphicObjects) {
            if ( this.GraphicObjects[i] === o) {
                this.GraphicObjects.splice(i, 1);
            }
        }
        o.destroy();
    }

    indexLineSwitch() {
        if (this.indexLine.isShow())
            this.indexLine.hide();
        else
            this.indexLine.show();
    }

    setWidth(w) {
        this.scale = w / this.origWidth;
        this.schImg.style.width = w;
        this.schImg.style.height = this.origHeight * this.scale;
        for (var i in this.GraphicObjects) {
            var o = this.GraphicObjects[i];
            o.update();
        }
    }

    width() {
        return parseInt(this.schImg.style.width);
    }

    height() {
        return parseInt(this.schImg.style.height);
    }

    incScale() {
        this.setWidth(this.width() + 500);
    }

    decScale() {
        var w = this.width();
        if (w <= 500)
            return;
        this.setWidth(this.width() - 500);
    }

    resetScale() {
        this.setWidth(this.defWidth);
    }

    noScale() {
        this.setWidth(this.origWidth);
    }

    keyPress(key) {
        switch(key) {
        case "+":
            this.incScale();
            return true;

        case "-":
            this.decScale();
            return true;

        case "*":
            this.resetScale();
            return true;

        case "/":
            this.noScale();
            return true;
        }
        return false;
    }

}

class Navigator extends SchematicPage {

}

class Editor extends SchematicPage {
    constructor(id, w, h,
                startIdx, endIdx,
                offset, step,
                mainDiv, schImg,
                editBox, messageBox) {
        super(id, w, h, startIdx, endIdx, offset, step, mainDiv, schImg);

        this.mode = 'start';
        this.editBox = editBox;
        this.messageBox = messageBox;
        this.startGraphPoint = NaN;
        this.editedArea = NaN;
        this.load();
    }

    onClick(p) {
        this.editBoxHide();

        if (this.indexLine.isShow()) {
            this.indexLine.hide();
            return;
        }

        if (this.isSelectedArea()) {
            this.resetSelection();
            return;
        }

        if (this.msgIsVisible()) {
            this.msgHide();
            return;
        }

        if (this.mode == 'start') {
            this.startGraphPoint = this.addPoint(p);
            this.mode = 'end';
            return;
        }

        if (this.mode == 'end') {
            var startPoint = this.startGraphPoint.point;
            this.remove(this.startGraphPoint);
            this.editedArea = this.addArea(new Rect(startPoint, p), "");
            var onEnter = function (name) {
                this.editedArea.setName(name);
                if (name[0] == "^") {
                    var rc = this.indexLine.makeByArea(this.editedArea);
                    if (rc) {
                        this.indexLine.show();
                        this.removeArea(this.editedArea);
                    }
                }
                this.editedArea = NaN;
            };
            var onEsc = function (name) { this.removeArea(this.editedArea); this.editedArea = NaN; };
            this.editBoxShow(p.toScaled(this.scale),
                             onEnter.bind(this),
                             onEsc.bind(this));
            this.mode = 'start';
        }
    }

    msg(msg) {
        this.messageBox.innerHTML = msg;
        this.messageBox.style.left = this.mousePos.x;
        this.messageBox.style.top = this.mousePos.y;
        this.messageBox.style.display = 'block';
    }

    msgHide() {
        this.messageBox.style.display = 'none';
        this.messageBox.innerHTML = "";
    }

    msgIsVisible() {
        return (this.messageBox.style.display == 'block') ? true : false;
    }

    editBoxShow(p, onEnter, onEsc, val = "") {
        this.editBox.style.left = this.left() + p.x;
        this.editBox.style.top = this.top() + p.y;
        this.editBox.style.display = 'block';
        this.editBox.value = val;
        this.editBox.focus();
        var f = function (ev) {
            if (ev.keyCode === 13) {
                var text = this.editBox.value;
                this.editBoxHide();
                onEnter(text);
                return false;
            }
            if (ev.keyCode === 27) {
                this.editBoxHide();
                onEsc();
                return false;
            }
            return true;
        };
        this.editBoxCb = f.bind(this);
        this.editBox.addEventListener("keyup", this.editBoxCb);
    }

    editBoxHide() {
        this.editBox.value = "";
        this.editBox.style.display = 'none';
        this.editBox.removeEventListener("keyup", this.editBoxCb);
    }

    resetSelection() {
        for (var i in this.areas) {
            var area = this.areas[i];
            area.unselect();
        }
    }

    selectArea(area) {
        this.resetSelection();
        area.select();
    }

    isSelectedArea() {
        for (var i in this.areas) {
            var area = this.areas[i];
            if (area.selected)
                return true;
        }
        return false;
    }


    keyPress(key) {
        var rc = super.keyPress(key);
        if (rc)
            return;

        switch(key) {
        case "Delete":
            for (var i in this.areas) {
                var area = this.areas[i];
                if (area.selected)
                    this.removeArea(area);
            }
            return true;
        }
        return false;
    }

    save() {
        var dataAreas = [];
        for (var i in this.areas) {
            var area = this.areas[i];
            var data = area.serialize();
            if (data)
                dataAreas.push(data);
        }

        var dataIndexLine = this.indexLine.serialize();

        var f = function (data) {
            var ret;
            eval('ret = ' + data);
            if (ret['result'] == 'ok')
                alert("save successfully");

            if (ret['result'] != 'ok')
                alert(data);
        }
        mk_query("save_page",
                 {'mod': 'page',
                  'id': this.id,
                  'areas': JSON.stringify(dataAreas),
                  'index_line': dataIndexLine ? JSON.stringify(dataIndexLine) : "",
                 },
                 f, true);
    }

    load() {
        var f = function (data) {
            var ret;
            eval('ret = ' + data);
            if (ret['result'] != 'ok')
                alert(data);

            for (var i in ret['link_points']) {
                var r = ret['link_points'][i]['rect'];
                var name = ret['link_points'][i]['to'];
                var rect = new Rect(new Point(r['left'], r['top']),
                                    new Point(parseInt(r['left']) + parseInt(r['width']),
                                              parseInt(r['top']) + parseInt(r['height'])));
                this.addArea(rect, name);
            }

            for (var i in ret['items_list']) {
                var r = ret['items_list'][i]['rect'];
                var name = ret['items_list'][i]['name'];
                var rect = new Rect(new Point(r['left'], r['top']),
                                    new Point(parseInt(r['left']) + parseInt(r['width']),
                                              parseInt(r['top']) + parseInt(r['height'])));
                this.addArea(rect, name);
            }
        }

        mk_query("load_page",
                 {'mod': 'page',
                  'id': this.id},
                 f.bind(this), true);
    }
}
