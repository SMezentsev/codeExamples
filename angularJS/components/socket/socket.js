

(function(){
    'use strict';
//    if( window.MozWebSocket ) {
//        window.WebSocket = window.MozWebSocket;
//    }
    angular.module('app').
    factory('$socket',[
    '$timeout','$interval','$q','$rootScope'
    ,function(
        $timeout,$interval,$q,$rootScope
        ){

        var _token = 't73CAqdS0uu3oBqZruYwnxN11oTmTrjj';//$appConfig.user('token');

        var ss = {};

        $rootScope.$on('$locationChangeStart', function() {
            $.each(ss,function(id,s){
                $.each(s.subscribes,function(i,sub){
                    if (sub.autounsub=='location') {
                        var params = sub.params;
                        _connect(s,function () {
                            params.mode = 'unsub';
                            _send(s,params);
                            $timeout(function(){
                                $.del(s.subscribes,i);
                            });
                        });
                    }
                });
            });
//        $timeout(function(){
//            console.log(ss);
//        })
        });

        return function(server,id){

//console.log('d',server,'rf',$appConfig.cloud(server),'d')

            id = id|| og.random();
            //server = server||$appConfig.cloud(server)
            ss[id] = ss[id]||{
                server: 'ws://_.com:3124/echo',
                socket:null,
                isConnected:null,
                connectionQuery:[],
                num:0,
                commands:{},
                subscribes:{},
                listeners:{},
                pub: {
                    id: id,

                    /**
                     * @param String cmd Command
                     * @param {} params Command parameters
                     * @params function Callback when received answer for this command
                     */
                    command: function(cmd,params,callback){
                        _command(ss[id],cmd,params,callback);
                    },
                    sendFile: function(file,events){
                        _sendFile(ss[id],file,events);
                    },
                    listen: function(cmd,callback,stopEvent){
                        return _listen(ss[id],cmd,callback,stopEvent);
                    },
                    subscribe: function(cmd,params,callback,unsubType){
                        _subscribe(ss[id],cmd,params,callback,unsubType);
                    },
                    unsubscribe: function(cmd,params){
                        _unsubscribe(ss[id],cmd,params);
                    }
                }
            };

            return ss[id].pub;
        };

        function _command(s,cmd,params,callback) {
            params.cmd = cmd;
            _connect(s,function () {
                _send(s,params,callback,'command');
            });
        }

        function _subscribe(s,cmd,params,callback,unsubType) {
            _connect(s,function () {
                params.cmd = cmd;
                params.mode = 'sub';
                _send(s,params,callback,'subscribe',unsubType);
            });
        }

        function _unsubscribe(s,cmd,params){
            $.delInBy(s.subscribes,'cmd',cmd);
            _connect(s,function () {
                params.cmd = cmd;
                params.mode = 'unsub';
                _send(s,params);
            });
        }

        function _send(s,data,callback,type,typeParam) {
            var params = angular.copy(data);
            data.num = ++s.num;
            //data.token = _token;
            s.socket.send(angular.toJson(data));
            // check callback type and send to handling
            if (callback) {
                if (type=='subscribe') {
                    s.subscribes[s.num] = {
                        num: s.num,
                        autounsub: typeParam||'location',
                        cmd: data.cmd,
                        params: params,
                        fn: callback
                    };
                } else if (type=='command'){
                    s.commands[s.num] = callback;
                }
            }
        }

        function _sendFile(s,file,events) {

            _command(s,'file',{
                file: file.name,
                time: file.lastModified||file.lastModifiedDate.getTime()||new Date().getTime(),
                size: file.size,
                response: true
            },function(data){
                //console.log('206')
//console.log('send',data);
                if (data.code==206) {
                    _sendBinaryChunked(s,file.binary,{
                        chunk: data.chunk||500000,
                        offset: data.position,
                        events: events
                    });

                } else if (data.code==302 && data.cmd=='file_transfer') {
                    //console.log('s333333')
                    if (events.done)
                        events.done(data);

                } else if (data.code==302 && data.cmd=='file') {
                    //console.log('done2!');
                    if (events.done)
                        events.done(data);
                }
            });

        }



        function _sendBinaryChunked(s,binary,options) {

            var events = options.events||{};

            var chunks = [];

            var chunksCount = Math.ceil((binary.byteLength-options.offset)/options.chunk);

            for (var c=0;c<chunksCount;c++) {
                var start = options.offset+c*options.chunk;
                var end = start + options.chunk;
                if (end>binary.byteLength)
                    end = binary.byteLength;
                if (start<end)
                    chunks.push(binary.slice(start,end));
            }

            var listenTimeout;
            var cancelListenTimeout = function(){
                if (listenTimeout) {
                    $timeout.cancel(listenTimeout);
                }
            };
            var refreshListenTimeout = function(){
                cancelListenTimeout();
                listenTimeout = $timeout(function(){
                    if (events.error)
                        events.error('timeout');
                    stopListen();
                },30000);
            };
            var stopListen = _listen(s,function(data){
                return data.cmd=='file_transfer';
            },function(data){
                stopListen();
                cancelListenTimeout();
                if (data.code==302) {
                    if (events.done)
                        events.done(data);
                }
            });

            var loadedBytes = 0;
            var sendNextChunk = function(processCallback){
                if (chunks.length) {
                    var chunk = chunks.shift();

                    refreshListenTimeout();

                    _sendData(s,chunk,{
                        process: function(bytes){
                            if (processCallback) {
                                processCallback(options.offset + loadedBytes + bytes);
                            }
                        },
                        done: function(){
                            loadedBytes += chunk.byteLength;

                            //$timeout(function(){
                            sendNextChunk(processCallback);
                            //},100);
                        }
                    });
                }
            };

//        $timeout(function(){
            $q.all(chunks).then(function(){
                sendNextChunk(events.process);
            });
//        });
        }

        function _sendData(s,data,events) {

            var length = data.byteLength!=undefined ? data.byteLength : data.length;

            s.socket.send(data);

            var unlisten = _listen(s,function(answ){
                return answ.cmd=='file' && answ.code==206;
            },function(){
                unlisten();
                if (events.done)
                    events.done();

            });

            var bytesSend = 0;
            var sending = setInterval(function(){
                if (s.socket.bufferedAmount==0) {
                    if (events.process)
                        events.process(bytesSend);
                    clearInterval(sending);
                    setTimeout(function(){
                        if (events.bufferEmpty)
                            events.bufferEmpty();
                    },100);
                } else {
                    var bs = length-s.socket.bufferedAmount;
                    if (bs!=bytesSend) {
                        bytesSend = bs;
                        if (events.process)
                            events.process(bytesSend);
                    }
                }
            },50);
        }

        function _listen(s,condition,callback,stopEvent) {

            var lid = og.random();

            s.listeners[lid] = {
                where: condition,
                fn: callback
            };

            var unlisten = function(){
                $.del(s.listeners,lid);
            };

            if (stopEvent) {
                var cancel = $rootScope.$on(stopEvent,function(){
                    unlisten();
                    cancel();
                });
            }

            return unlisten;
        }

        function _handle(s,message) {
            var data = angular.fromJson(message.data);
            if (data.num) {
                if (s.commands[data.num]) {
                    // run once
                    s.commands[data.num](data);
                    $timeout(function(){
                        $.del(s.commands,data.num);
                    });
                } else if (s.subscribes[data.num]) {
                    // run everytime until unsubscribed
                    s.subscribes[data.num].fn(data);
                }
            }
            if (data.cmd) {
                // check listeners
                $.each(s.listeners,function(i,listener){
                    if (listener.where && listener.where(data))
                        listener.fn(data);
                    else if (listener.cmd && data.cmd==listener.cmd)
                        listener.fn(data);
                });
            }


        }

        function _connect(s,onConnectCallback,auth) {

            auth = s.server.auth==undefined ? true : !!s.server.auth;

            if (!s.isConnected) {
                // query runs after connection and successful auth
                s.connectionQuery.push(onConnectCallback);
                // opn socket if not yet
                if (!s.socket) {
                    s.socket = new WebSocket('ws://_:3124/echo', s.server.protocol||[]);
                    //s.socket.binaryType = 'arraybuffer';

                    var successfulConnection = function(){
                        s.isConnected = true;
                        // set handler
                        s.socket.onmessage = function(message){
                            _handle(s,message);
                        };
                        $.each(s.connectionQuery,function(i,fn){
                            fn();
                        });
                        $timeout(function(){
                            s.connectionQuery = [];
                        });
                    };

                    // wait connection
                    s.socket.onopen = function() {
                        s.socket.onopen = null;
                        if (auth) {
                            // auth command
                            _send(s,{
                                cmd:'auth',
                                token: s.server.hardToken ? s.server.hardToken : _token
                            });
                            // wait response
                            s.socket.onmessage = function(message){
                                var data = angular.fromJson(message.data);
                                if (data.code==200) {
                                    // auth successful response
                                    successfulConnection();
                                } else {
                                    s.socket.close();
                                }
                            };
                        } else {
                            successfulConnection();
                        }
                    };
                    s.socket.onerror = function(){
                        console.log('socket error');
                        s.socket.close();
                    };
                    window.SOCK = s.socket;
                }
            } else if (s.socket.readyState==WebSocket.CLOSED) {
                s.isConnected = null;
                s.connectionQuery = [];
                s.socket = null;
                _connect(s,onConnectCallback);

            } else if (s.socket.readyState==WebSocket.CLOSING) {
                s.isConnected = null;
                s.connectionQuery = [];
                s.socket.onclose = function(){
                    s.socket.onclose = null;
                    _connect(s,onConnectCallback);
                }
            } else {
                onConnectCallback();
            }
        }
    }]);

})();
