import sympy as sp
import time
start = time.time()


t, s = sp.symbols('t s')
exp = sp.exp

y1=sp.exp(3*t)*(sp.cos(sp.sqrt(2)*t)*sp.Matrix([0,1])-sp.sin(sp.sqrt(2)*t)*sp.Matrix([sp.sqrt(2),0]))
y2=sp.exp(3*t)*(sp.sin(sp.sqrt(2)*t)*sp.Matrix([0,1])+sp.cos(sp.sqrt(2)*t)*sp.Matrix([sp.sqrt(2),0]))

psi = sp.Matrix([[y1[0], y2[0]], [y1[1], y2[1]]])
g = sp.Matrix([sp.exp(s), s])

integrand = psi * psi.subs(t, s).inv() * g

y = sp.simplify(sp.integrate(integrand, (s, 0, t)))
print(y)
print(f"Tempo total: {time.time() - start:.2f} segundos")

