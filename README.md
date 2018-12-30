# iTop-CN
iTop in chinese  

## 安装要求  
```
php5.6+  
mysql 5.6+ 或 mariadb 10.0+  
```

## 改进  
```
1、除了安装界面，汉化率95%左右  
2、添加字体droidsansfallback，彻底解决pdf导出乱码问题  
3、解决jquery-ui 中的日历无法调用的问题  
4、删除了中英文之外的其它语言包  
5、默认时区改为Asia/Shanghai，更贴近中文用户  
```

## 安装指南（docker + apache2 + php7.0）

```
wget https://raw.githubusercontent.com/purplegrape/iTop-CN/master/Dockerfile
docker build -t itop .
docker run -d -p 80:80 itop
```
注： 请自行准备数据库  


## 其它
已将汉化成果贡献给了官方，https://github.com/Combodo/iTop  
但官方版本依然存在一些小问题，仍在积极解决中，比如pdf导出乱码，日历问题  
