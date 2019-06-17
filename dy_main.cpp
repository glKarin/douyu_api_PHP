#include <sys/socket.h>
#include <sys/types.h>
#include <unistd.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <cassert>
#include <cstring>
#include <cstdlib>
#include <ctime>
#include <string>
#include <iostream>
#include <unordered_map>
#include <vector>

#define ID_IP "119.96.201.28"
#define ID_PORT 8601
#define ID_MSG_CLIENT_TO_SERVER 689
#define ID_MSG_SERVER_TO_CLIENT 690
#define ID_DY_VER "20190612"
#define ID_PINGPONG_INTERVAL 45
#define ID_GID "-9999"

using idRespField_t = std::unordered_map<std::string, std::string>;

namespace id
{
    std::vector<std::string> string_split(const std::string &src, const std::string &split)
    {
        std::vector<std::string> r;
        std::string::size_type pos;
        std::string::size_type last_pos;
        std::string::size_type len, full_len;

        last_pos = 0;
        len = split.length();
        full_len = src.length();
        while(last_pos <= full_len - 1)
        {
            pos = src.find(split, last_pos);
            if(pos != std::string::npos)
            {
                std::string s;
                 if(pos > last_pos)
                    s = src.substr(last_pos, pos - last_pos);
                r.push_back(s);
                if(pos < full_len - 1)
                    last_pos = len + pos;
                else
                    last_pos = std::string::npos;
            }
            else
            {
                std::string s = src.substr(last_pos);
                r.push_back(s);
                last_pos = std::string::npos;
            }
        }
        if(last_pos == full_len)
            r.push_back(std::string());
        return r;
    }

    idRespField_t make_resp(const std::string &msg)
    {
        idRespField_t r;
        std::vector<std::string> items;
        items = string_split(msg, "/");
        for(const auto &item : items)
        {
            std::vector<std::string> sitems = string_split(item, "@=");
            r.insert({sitems[0], sitems[1]});
        }
        return r;
    }
}

class ByteArray
{
public:
    ByteArray(uint32_t len = 1024)
    : m_length{len},
    m_data{nullptr},
    m_pos{0}
    {
        assert(m_length > 0);
        m_data = new char[m_length];
    }
    ByteArray(ByteArray &&o)
    : m_length(o.m_length),
     m_data(o.m_data),
     m_pos(o.m_pos)
    {
        o.m_data = nullptr;
    }
    ByteArray(const ByteArray &o)
    : ByteArray(o.m_length)
    {
        m_pos = o.m_pos;
        memcpy(m_data, o.m_data, sizeof(char) * m_pos);
    }
    virtual ~ByteArray()
    {
        delete[] m_data;
    }

    ByteArray & operator=(const ByteArray &o)
    {
        if(this == &o)
            return *this;

        delete[] m_data;
        m_pos = o.m_pos;
        m_length = o.m_length;
        m_data = new char[m_length];
        memcpy(m_data, o.m_data, sizeof(char) * m_pos);
        return *this;
    }

    ByteArray & operator=(ByteArray &&o)
    {
        if(this == &o)
            return *this;

        delete[] m_data;
        m_pos = o.m_pos;
        m_length = o.m_length;
        m_data = o.m_data;
        o.m_data = nullptr;
        return *this;
    }

    ByteArray & append(char ch)
    {
        assert(m_pos < m_length - 1);
        m_data[m_pos] = ch;
        if(m_pos < m_length - 1)
            m_pos++;
        return *this;
    }
    ByteArray & append(const std::string &str)
    {
        assert(m_pos < m_length - 1);

        int len;
        int limit;

        len = str.length();
        limit = m_length - m_pos;
        len = len > limit ? limit : len;
        memcpy(m_data + m_pos, str.c_str(), sizeof(char) * len);
        m_pos += len;
        return *this;
    }
    ByteArray & append(uint32_t len, const char *data)
    {
        assert(m_pos < m_length - 1);

        int limit;

        limit = m_length - m_pos;
        limit = len > limit ? limit : len;
        memcpy(m_data + m_pos, data, sizeof(char) * limit);
        m_pos += limit;
        return *this;
    }
    char * data()
    {
        char *r;

        if(m_pos == 0)
            return nullptr;

        r = new char[m_pos];
        memcpy(r, m_data, sizeof(char) * m_pos);

        return r;
    }
    uint32_t size() const
    {
        return m_length;
    }
    uint32_t length() const
    {
        return size();
    }
    uint32_t used() const
    {
        return m_pos;
    }

    const char * data() const
    {
        return m_data;
    }
    char * rawData()
    {
        return m_data;
    }

    private:
    uint32_t m_length;
    char *m_data;
    uint32_t m_pos;
};

struct idMsgResp_s
{
    int32_t len;
    int16_t type;
    std::string data;

    idMsgResp_s(int32_t len = 0, int16_t type = 0, std::string data = "")
    : len{len},
    type{type},
    data{data}
    {
    }

    ByteArray Pack()
    {
        int len;
        uint16_t s;
        ByteArray r;

        len = data.length() + 9;

        r.append(4, (char *)&len);
        r.append(4, (char *)&len);
        s = ID_MSG_CLIENT_TO_SERVER;
        r.append(2, (char *)&type);
        r.append((char)0);
        r.append((char)0);
        r.append(data);
        r.append('\0');

        return r;
    }

    void Unpack(const char *msg)
    {
        const char *p;

        p = msg;
        memcpy(&len, p, 4);
        p += 4;
        p += 4;
        memcpy(&type, p, 2);
        p += 2;
        p += 1;
        p += 1;
        data.clear();
        data.append(p);
    }
};

class idResp
{
    public:
    idResp();
    virtual ~idResp();

    protected:
    virtual void FromRawData(const std::string &data) = 0;

    protected:
    idRespField_t m_resp;
    std::string type;
};

class idDYD
{
    public:
    idDYD();
    virtual ~idDYD();
    bool IsConnected() const;
    bool IsLogined() const;
    std::string CurrentRoom() const;
    void Connect();
    void Disconnect();
    void Login(const std::string &rid);
    void Logout();
    void Pingpong();
    void Run();

    private:
    int SendMsg(const std::string &msg);
    int RecvMsg(std::string &msg);
    ByteArray PackData(const std::string &msg) const;
    idMsgResp_s UnpackData(const char *msg) const;

    private:
    int m_socket;
    std::string m_rid;
    bool m_isLogin;
};

idDYD::idDYD()
    : m_socket{0},
    m_isLogin{false}
{
}

idDYD::~idDYD()
{
    Disconnect();
}

void idDYD::Connect()
{
    if(m_socket == 0)
    {
        m_socket = socket(AF_INET, SOCK_STREAM, 0);
    }
    struct sockaddr_in addr = {
        AF_INET,
        htons(ID_PORT),
        {
            inet_addr(ID_IP)
        }
    };
    connect(m_socket, (sockaddr *)&addr, sizeof(struct sockaddr_in));
}

bool idDYD::IsConnected() const
{
    return m_socket > 0;
}

bool idDYD::IsLogined() const
{
    return IsConnected() && m_isLogin;
}

void idDYD::Login(const std::string &rid)
{
    int len;
    std::string r;

    if(!IsConnected())
        return;
    std::string msg = "type@=loginreq/roomid@=";
    msg += rid;
    msg += "/ver@=";
    msg += ID_DY_VER;
    msg += "/ct@=0/";
    m_rid = rid;

    len = SendMsg(msg);
    std::cout << len << std::endl;
    assert(len > 0);

    len = RecvMsg(r);
    std::cout << len << std::endl;
    assert(len > 0);
    std::cout << r << std::endl;
    id::make_resp(r);

    msg = "type@=joingroup/rid@=";
    msg += rid;
    msg += "/gid@=";
    msg += ID_GID;
    msg += "/";
    len = SendMsg(msg);
    std::cout << len << std::endl;
    assert(len > 0);

    m_isLogin = len > 0;
}

void idDYD::Pingpong()
{
    int len;
    time_t ts;

    if(!IsLogined())
        return;
    ts = time(nullptr);
    std::string msg = "type@=keeplive/tick@=";
    msg += std::to_string(ts);
    msg += "/ct@=0/";

    len = SendMsg(msg);
    if(len <= 0)
        std::cerr << "pingpong error" << std::endl;
}

void idDYD::Disconnect()
{
    if(IsConnected())
    {
        shutdown(m_socket, 2);
        m_socket = 0;
    }
    m_isLogin = false;
}

void idDYD::Run()
{
    fd_set fdset;
    int num;
    std::string msg;

    if(!IsLogined())
        return;

    struct timeval tv = {5, 0};
    while(1)
    {
        FD_ZERO(&fdset);
        FD_SET(m_socket, &fdset);
        num = select(m_socket + 1, &fdset, nullptr, nullptr, &tv);
        if(num < 0)
            std::cerr << "socket error" << std::endl;
        else if(num == 0)
            std::clog << "no socket read" << std::endl;
        else
        {
            if(FD_ISSET(m_socket, &fdset))
            {
                RecvMsg(msg);
                std::cout << msg << std::endl;
            }
        }
        sleep(5);
        Pingpong();
    }
}

void idDYD::Logout()
{
    int len;

    if(!IsLogined())
        return;
    std::string msg = "type@=logout/";

    len = SendMsg(msg);
    if(len <= 0)
        std::cerr << "logout error" << std::endl;
}

ByteArray idDYD::PackData(const std::string &msg) const
{
    uint32_t len;
    uint16_t type;

    len = msg.length() + 9;
    type = ID_MSG_CLIENT_TO_SERVER;

    ByteArray data = idMsgResp_s(len, type, msg).Pack();

    return data;
}

idMsgResp_s idDYD::UnpackData(const char *msg) const
{
    idMsgResp_s resp;
    resp.Unpack(msg);
    return resp;
}

int idDYD::SendMsg(const std::string &msg)
{
    if(!IsConnected())
        return -1;
    const ByteArray b = PackData(msg);
    return send(m_socket, b.data(), b.used(), 0);
}

int idDYD::RecvMsg(std::string &msg)
{
    int len;
    if(!IsConnected())
        return -1;

    ByteArray b;
    len = recv(m_socket, b.rawData(), b.length(), 0);
    if(len > 0)
    {
        idMsgResp_s resp = UnpackData(b.rawData());
        msg = resp.data;
    }
    return len;
}

int main(int argc, char *argv[])
{
    std::string rid;
    idDYD dyd;

    rid = argc > 1 ? argv[1] : "149985";
    dyd.Connect();
    dyd.Login(rid);
    dyd.Run();
    return 0;
}
