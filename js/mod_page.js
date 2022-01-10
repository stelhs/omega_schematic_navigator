class Point {
    constructor(x, y) {
        this.x = parseInt(x);
        this.y = parseInt(y);
    }

    toScaled(scale) {
        return new Point(Math.round(this.x * scale),
                         Math.round(this.y * scale));
    }

    fromScaled(scale) {
        return new Point(Math.round(this.x / scale),
                         Math.round(this.y / scale));
    }

    minus(point) {
        return new Point(this.x - point.x,
                         this.y - point.y);
    }

    plus(point) {
        return new Point(this.x + point.x,
                         this.y + point.y);
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
        this.borderType = "solid";
        this.borderWidth = 3;
        this.color = "blue";
        this.updateBorder();
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

    updateBorder() {
        var border = this.borderType + ' ' +
                     this.borderWidth +
                     'px ' + this.color;
        this.div.style.border = border;
    }

    setClass(cssClass) {
        this.div.className = cssClass;
    }

    setColor(color) {
        this.color = color;
        this.updateBorder();
        this.update();
    }

    setBorderWidth(width) {
        this.borderWidth = width;
        this.updateBorder();
        this.update();
    }

    setBorderType(type) {
        this.borderType = type;
        this.updateBorder();
        this.update();
    }

    setMouseCursor(cursor) {
        this.div.style.cursor = cursor;
    }

    destroy() {
        this.div.remove();
    }
}

class GraphicObjectRect extends GraphicObject {
    constructor(editor, rect) {
        super(editor, 'rect');
        this.setRect(rect)
        this.div.style.position = "absolute";
        this.div.style.display = "block";
    }

    setRect(rect) {
        this.rect = rect;
        var s = this.editor.scale;
        this.div.style.left = this.editor.left() + rect.toScaled(s).left();
        this.div.style.top = this.editor.top() + rect.toScaled(s).top();
        this.div.style.width = rect.toScaled(s).width() - this.borderWidth * 2;
        this.div.style.height = rect.toScaled(s).height() - this.borderWidth * 2;
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
        this.div.style.position = "absolute";
        this.div.style.display = "block";
    }

    setPoint(point) {
        this.point = point;
        var s = this.editor.scale;
        this.div.style.left = this.editor.left() +
                              point.toScaled(s).x - this.borderWidth;
        this.div.style.top = this.editor.top() +
                              point.toScaled(s).y - this.borderWidth;
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
        var p = this.graphRect.rect.bottomRight();
        var f = function(name) {
            this.setName(name);
        }
        this.editor.editBoxShow(p, f.bind(this),
                                   function () {},
                                   this.name);
    }

    select() {
        this.graphRect.setColor("red");
        this.selected = true;
    }

    unselect() {
        this.graphRect.setColor("blue");
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
    constructor(editor, startIdx, endIdx) {
        this.editor = editor;
        this.startIdx = startIdx;
        this.endIdx = endIdx;
        this.offset = NaN;
        this.step = NaN;
        this.grid = [];
    }

    makeByArea(area) {
        var rect = area.graphRect.rect;
        var m = area.name.match(/\^(\d+) (\d+) (\d+) (\d+)/);
        if (m.length < 5)
            return NaN;

        var areaStart = parseInt(m[1]);
        var areaEnd = parseInt(m[2]);
        this.startIdx = parseInt(m[3]);
        this.endIdx = parseInt(m[4]);
        var areaLeft = rect.left();
        var areaRight = rect.right();

        this.step = (areaRight - areaLeft) / (areaEnd - areaStart);
        this.offset = areaLeft - (areaStart - this.startIdx) * this.step;
        return true;
    }

    setParams(offset, step) {
        this.offset = offset;
        this.step = step;
    }

    offsetByIndex(idx) {
        if (idx < this.startIdx)
            return NaN;

        if (idx > this.endIdx)
            return NaN;

        var cnt = idx - this.startIdx;
        return this.offset + cnt * this.step;
    }

    show() {
        if (!this.step)
            return;

        for (var i = this.startIdx; i <= this.endIdx; i++) {
            var offIdx = this.offsetByIndex(i);
            var p1 = new Point(offIdx - 1, 50);
            var p2 = new Point(offIdx + 1, this.editor.origHeight - 50);
            var o = this.editor.addRect(new Rect(p1, p2));
            o.setBorderWidth(1);
            o.setColor("green");
            this.grid.push(o);
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
        return {'start_index': this.startIdx,
                'end_index': this.endIdx,
                'offset': this.offset,
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

        this.defWidth = this.width();
        this.defHeight = this.height();
        this.setWidth(this.defWidth);

        this.mainDiv = mainDiv;
        this.GraphicObjects = [];
        this.mousePos = new Point(0,0);

        this.indexLine = new IndexLine(this, startIdx, endIdx);
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

    load(onLoaded) {
        this.onLoaded = onLoaded;
        var f = function (data) {
            var ret;
            eval('ret = ' + data);
            if (ret['result'] != 'ok')
                alert(data);

            var listLinkPoints = [];
            for (var i in ret['link_points']) {
                var r = ret['link_points'][i]['rect'];
                var from = ret['link_points'][i]['from'];
                var to = ret['link_points'][i]['to'];
                var link = ret['link_points'][i]['link'];
                var description = ret['link_points'][i]['description'];
                var rect = new Rect(new Point(r['left'], r['top']),
                                    new Point(parseInt(r['left']) + parseInt(r['width']),
                                              parseInt(r['top']) + parseInt(r['height'])));
                var lp = new LinkPoint(this, rect, from ,to, link, description);
                listLinkPoints.push(lp);
            }

            var listItems = [];
            for (var i in ret['items_list']) {
                var r = ret['items_list'][i]['rect'];
                var name = ret['items_list'][i]['name'];
                var desc = ret['items_list'][i]['description'];
                var id = ret['items_list'][i]['id'];
                var rect = new Rect(new Point(r['left'], r['top']),
                                    new Point(parseInt(r['left']) + parseInt(r['width']),
                                              parseInt(r['top']) + parseInt(r['height'])));
                var item = new SchematicItem(this, rect, name, desc, id);
                listItems.push(item);
            }
            this.onLoaded(listLinkPoints, listItems);
        }

        mk_query("load_page",
                 {'mod': 'page',
                  'id': this.id},
                 f.bind(this), true);
    }

}

class LinkPoint {
    constructor(editor, rect, from, to, link, description) {
        this.editor = editor;
        this.rect = rect;
        this.from = parseInt(from);
        this.to = parseInt(to);
        this.link = link;
        this.description = description;
    }

    setGraphicRect(graphRect) {
        this.graphRect = graphRect;
        graphRect.setName(this.from + ' -> ' + this.to + '\n' + this.description);
        var f = function () { this.onClick(); };
        graphRect.setOnClick(f.bind(this));
    }

    onClick() {
        if (this.link)
            location.href = this.link;
    }
}

class SchematicItem {
    constructor(page, rect, name, desc, id) {
        this.page = page;
        this.rect = rect;
        this.name = name;
        this.desc = desc;
        this.id = id;
    }

    setGraphicRect(graphRect) {
        this.graphRect = graphRect;
        graphRect.setName(this.name + "\n" + this.desc);
        var f = function () { this.onClick(); };
        graphRect.setOnClick(f.bind(this));
    }

    onClick() {
        this.page.msgShow(this.rect.topLeft(),
                          this.name + "<br>" + this.desc);
    }
}

class Navigator extends SchematicPage {
    constructor(id, w, h,
                startIdx, endIdx,
                offset, step,
                mainDiv, schImg,
                popupMsgBox) {
        super(id, w, h, startIdx, endIdx, offset, step, mainDiv, schImg);
        this.messageBox = popupMsgBox;
        this.linkPoints = [];
        this.items = [];
        this.load();

        this.indexSelector = NaN;
        this.linkPointSelector = NaN;
        this.needToHighligntLinkPoint = NaN;
        this.needToHighligntItem = NaN;
    }

    addLinkPoint(lp) {
        var graphRect = this.addRect(lp.rect);
        graphRect.setBorderWidth("1");
        graphRect.setColor("red");
        graphRect.setMouseCursor("pointer");

        lp.setGraphicRect(graphRect);
        this.linkPoints.push(lp);
        return lp;
    }

    addItem(item) {
        var graphRect = this.addRect(item.rect);
        graphRect.setBorderWidth("1");
        graphRect.setMouseCursor("pointer");
        item.setGraphicRect(graphRect);
        this.items.push(item);
        return item;
    }

    setHighlightLinkPoint(linkPointTo, linkPointFrom) {
        this.needToHighligntLinkPoint = [linkPointTo, linkPointFrom];
    }

    setHighlightItem(itemId) {
        this.needToHighligntItem = itemId;
    }

    load() {
        var f = function (linkPoints, items) {
            for (var i in linkPoints) {
                var lp = linkPoints[i];
                this.addLinkPoint(lp);
            }

            for (var i in items) {
                var item = items[i];
                this.addItem(item);
            }

            if (this.needToHighligntLinkPoint) {
                var lp = this.linkPointByTo(this.needToHighligntLinkPoint[0]);
                if (lp)
                    this.showLinkPointSelector(lp);
                else {
                    this.showIndexSelector(this.needToHighligntLinkPoint[1]);
                }
            }

            if (this.needToHighligntItem) {
                item = this.itemById(this.needToHighligntItem);
                if (item)
                    this.showItemSelector(item);
            }

        }
        super.load(f.bind(this));
    }

    onClick() {
        this.hideIndexSelector();
        this.hidelinkPointSelector();
        this.hideItemSelector();
        this.msgHide();
    }

    showIndexSelector(index) {
        this.hideIndexSelector();
        var offIdx = this.indexLine.offsetByIndex(index);
        if (offIdx === NaN)
            return;
        var rect = new Rect(new Point(offIdx - 25, 50),
                        new Point(offIdx + 25, this.origHeight - 50));
        this.indexSelector = this.addRect(rect);
        this.indexSelector.setColor("transparent");
        this.indexSelector.setClass("index_blinking");
        this.indexSelector.setBorderWidth(5);
    }

    hideIndexSelector() {
        if (!this.indexSelector)
            return;
        this.indexSelector.destroy();
        this.indexSelector = NaN;
    }

    showLinkPointSelector(lp) {
        this.hidelinkPointSelector();
        var rect = new Rect(lp.rect.topLeft().minus(new Point(10, 10)),
                            lp.rect.bottomRight().plus(new Point(10, 10)));
        this.linkPointSelector = this.addRect(rect);
        this.linkPointSelector.setColor("transparent");
        this.linkPointSelector.setClass("index_blinking");
        this.linkPointSelector.setBorderWidth(10);
        var f = function () { this.hidelinkPointSelector(); };
        this.linkPointSelector.setOnClick(f.bind(this));
    }

    showItemSelector(item) {
        this.hideItemSelector();
        var rect = new Rect(item.rect.topLeft().minus(new Point(10, 10)),
                            item.rect.bottomRight().plus(new Point(10, 10)));
        this.itemSelector = this.addRect(rect);
        this.itemSelector.setColor("transparent");
        this.itemSelector.setClass("index_blinking");
        this.itemSelector.setBorderWidth(10);
        var f = function () { this.hideItemSelector(); };
        this.itemSelector.setOnClick(f.bind(this));
    }

    hideItemSelector() {
        if (!this.itemSelector)
            return;
        this.itemSelector.destroy();
        this.itemSelector = NaN;
    }

    hidelinkPointSelector() {
        if (!this.linkPointSelector)
            return;
        this.linkPointSelector.destroy();
        this.linkPointSelector = NaN;
    }

    linkPointByTo(to) {
        for (var i in this.linkPoints) {
            var lp = this.linkPoints[i];
            if (lp.to == to)
                return lp;
        }
        return NaN;
    }

    itemById(id) {
        for (var i in this.items) {
            var item = this.items[i];
            if (item.id == id)
                return item;
        }
        return NaN;
    }

    msgShow(point, msg) {
        this.messageBox.innerHTML = msg;
        this.messageBox.style.border = "solid 2px green";
        this.messageBox.style.display = 'block';
        this.messageBox.style.left = this.left() + point.toScaled(this.scale).x;
        this.messageBox.style.top = this.top() + point.toScaled(this.scale).y;
        var f = function() {
            this.msgHide();
        }
        setTimeout(f.bind(this), 3000);
    }

    msgHide() {
        this.messageBox.style.display = 'none';
        this.messageBox.innerHTML = "";
    }

}

class Editor extends SchematicPage {
    constructor(id, w, h,
                startIdx, endIdx,
                offset, step,
                mainDiv, schImg,
                editBox, messageBox) {
        super(id, w, h, startIdx, endIdx, offset, step, mainDiv, schImg);

        this.areas = [];
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
            this.editBoxShow(p,
                             onEnter.bind(this),
                             onEsc.bind(this));
            this.mode = 'start';
        }
    }

    msgOk(msg) {
        this.messageBox.innerHTML = msg;
        this.messageBox.style.border = "solid 2px green";
        this.messageBox.style.display = 'inline';
        var f = function() {
            this.msgHide();
        }
        setTimeout(f.bind(this), 2000);
    }

    msgErr(msg) {
        this.messageBox.innerHTML = msg;
        this.messageBox.style.border = "solid 2px red";
        this.messageBox.style.display = 'inline';
        var f = function() {
            this.msgHide();
        }
    }

    msgHide() {
        this.messageBox.style.display = 'none';
        this.messageBox.innerHTML = "";
    }

    msgIsVisible() {
        return (this.messageBox.style.display == 'block') ? true : false;
    }

    editBoxShow(p, onEnter, onEsc, val = "") {
        this.editBox.style.left = this.left() + p.toScaled(this.scale).x;
        this.editBox.style.top = this.top() + p.toScaled(this.scale).y;
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

    indexLineSwitch() {
        if (this.indexLine.isShow())
            this.indexLine.hide();
        else
            this.indexLine.show();
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
    keyPress(key) {
        var rc = super.keyPress(key);
        if (rc)
            return;

        switch(key) {
        case "Delete":
        case "=":
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
        if (!dataIndexLine) {
            this.msgErr("Index line is not set");
            return;
        }

        var f = function (data) {
            var ret;
            eval('ret = ' + data);
            if (ret['result'] == 'ok')
                this.msgOk("save successfully");

            if (ret['result'] != 'ok')
                this.msgErr(data);
        }
        mk_query("save_page",
                 {'mod': 'page',
                  'id': this.id,
                  'areas': JSON.stringify(dataAreas),
                  'index_line': JSON.stringify(dataIndexLine),
                 },
                 f.bind(this), true);
    }

    load() {
        var f = function (linkPoints, items) {
            for (var i in linkPoints) {
                var lp = linkPoints[i];
                this.addArea(lp.rect, lp.to);
            }

            for (var i in items) {
                var item = items[i];
                this.addArea(item.rect, item.name);
            }
        }
        super.load(f.bind(this));
    }
}
