# standaloneV3.py
import argparse
import warnings
import mysql.connector
import numpy as np
import pandas as pd
# from loan_analyzer_standalone import (
#     get_db_connection, get_all_students, test12
# )
warnings.filterwarnings("ignore")  # para no contaminar la salida con mensajes


# Conexión con la base de datos
def get_db_connection():
    try:
        conn = mysql.connector.connect(
            host='localhost',        # o IP del servidor
            user='root',
            password='',
            database='portalusuarios'
        )
        return conn
    except mysql.connector.Error as err:
        print(f"Error de conexión a la base de datos: {err}")
        return None


def get_all_students():
    """
    Obtiene la lista de todos los estudiantes y sus datos académicos
    para permitir la selección de un estudiante específico
    """
    conn = get_db_connection()
    if not conn:
        return []
    
    cursor = conn.cursor(dictionary=True)
    
    try:
        query = """
        SELECT 
            u.id, 
            CONCAT(u.nome, ' ', u.sobrenome) AS nombre_completo,
            pe.gpa, 
            pu.nome AS universidad,
            cu.nome_curso AS curso,
            cu.salario_esperado,
            cu.custo_semestre
        FROM usuarios u
        JOIN perfil_estudante pe ON u.id = pe.usuario_id
        LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
        LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
        WHERE u.categoria = 'estudante'
        ORDER BY u.nome
        """
        
        cursor.execute(query)
        students = cursor.fetchall()
        return students
    
    except mysql.connector.Error as err:
        print(f"Error al consultar estudiantes: {err}")
        return []
    
    finally:
        cursor.close()
        conn.close()


def test1(emprestimo, juros, GPA, sex, university, course, salary_percentage=0.15, max_months=120, max_payment_ratio=1.8):
    """
    Will see if the person can pay the loan in the specified timeframe without paying more than max_payment_ratio times the initial loan

    DISCLAIMER: 
    1. We are assuming a fixed interest rate for the entire period.
    2. We are assuming a fixed monthly payment of a percentage of the salary.
    
    Parameters:
    emprestimo (float): The loan amount
    juros (float): Monthly interest rate
    GPA (float): Student's GPA
    sex (string): Student's sex ('male' or 'female')
    university (string): University name
    course (string): Course name
    salary_percentage (float): Percentage of salary used for monthly payment (default: 0.15)
    max_months (int): Maximum number of months to pay the loan (default: 120)
    max_payment_ratio (float): Maximum ratio of total payment to loan amount (default: 1.8)
    
    Returns: 
    dict: Resultados del análisis del préstamo
    """
    def salary_vector_prediction(GPA, sex, university, course, months_needed=120):
        """
        Predicts a salary vector over specified months with stepwise (non-continuous) growth.
        The salary increases once per year and stays fixed for 12 months.
        
        Parameters:
        GPA (float): Student's GPA
        sex (string): Student's sex ('male' or 'female')
        university (string): University name
        course (string): Course name
        months_needed (int): Number of months to predict (default: 120)
        
        Returns:
        list: Vector of monthly salaries
        """
        
        def base_salary(GPA, sex, university, course):
            # Estos son salarios anuales
            salary_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 10000,
                    "Ingeniería Quimica": 12500,
                    "Ingeniería Computacion y Tecnologias de la informacion": 15000,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 10000,
                    "Ingenieria Mecanica": 10000,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 77300,
                    "Mechanical Engineering": 89668,
                }
            }

            gpa_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 3.4,
                    "Ingeniería Quimica": 3.4,
                    "Ingeniería Computacion y Tecnologias de la informacion": 3.4,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 3.0,
                    "Ingenieria Mecanica": 3.0,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 3.8,
                    "Mechanical Engineering": 3.8,
                }
            }

            # Obtener el salario anual base
            raw_salary = salary_data.get(university, {}).get(course, 50000) #default salary is 50000
            gpa_avg = gpa_data.get(university, {}).get(course, 3.5) #default GPA is 3.5

            if sex == "male":
                beta = 0.12
            elif sex == "female":
                beta = 0.12
            else:
                raise ValueError("Invalid value for 'sex'. It must be either 'male' or 'female'.")

            # Calcular el salario anual ajustado por GPA
            annual_salary = raw_salary * np.exp(beta * (GPA - gpa_avg))
            
            # Convertir a salario mensual
            monthly_salary = annual_salary / 12
            
            return monthly_salary

        def get_annual_growth(university, course):
            growth_data = {
                "University A": {
                    "Engineering": 0.10,
                    "Arts": 0.05,
                },
                "University B": {
                    "Engineering": 0.12,
                    "Arts": 0.07,
                },
            }
            return growth_data.get(university, {}).get(course, 0.1)

        # --- Core logic ---
        initial_monthly_salary = base_salary(GPA, sex, university, course)
        annual_growth = get_annual_growth(university, course)

        salary_vector = []
        current_salary = initial_monthly_salary

        for month in range(months_needed):
            salary_vector.append(current_salary)
            if (month + 1) % 12 == 0:  # increase salary once per year
                current_salary *= (1 + annual_growth)

        return salary_vector
    
    # Asegurarnos de que el vector de salarios sea suficientemente largo
    salary_vec = salary_vector_prediction(GPA, sex, university, course, months_needed=max_months)

    def update_divida(months, divida, salary_vec, juros):
        monthly_payment = salary_vec[months] * salary_percentage  # Percentage of income
        payment = min(monthly_payment, divida)
        divida = (divida - payment) * (1 + juros)

        return max(divida, 0)  

    divida_list = []
    total_pago_list = []
    divida = emprestimo
    total_pago = 0
    months = 0
    
    while divida > 0 and months < max_months and total_pago < emprestimo * max_payment_ratio:
        divida = update_divida(months, divida, salary_vec, juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary_vec[months] * salary_percentage
        months += 1

    was_paid = divida_list[-1] == 0
    months_left = max(max_months-months, 0)
    expected_return = total_pago

    return {
        "was_paid": was_paid,
        "months_left": months_left,
        "expected_return": expected_return,
        "remaining_debt": divida_list[-1]
    }

def test2(threshold, emprestimo, expected_return, country="BR", risk1=None, risk2=None, risk3=None, risk4=None):
    """
    Check if the expected value is above the profit threshold 
    Expected Value = (1-risk) * (expected return) + (risk) *(-emprestimo)
    
    check Expected Value > threshold * emprestimo
    
    Parameters:
    threshold (float): The threshold for risk worthiness.
    emprestimo (float): The loan amount.
    expected_return (float): The expected return.
    
    Returns:
    dict: A dictionary containing the results of the risk analysis.
    """
    def risk(country, risk1=None, risk2=None, risk3=None, risk4=None):
        """
        Calculate the risk based on the country and other factors
        
        Parameters:
        emprestimo (float): The loan amount.
        expected_return (float): The expected return.
        
        Returns:
        float: The calculated risk.
        """
        # Default country-level risk estimates
        base_risk = {
            "BR": 0.35,
            "US": 0.12,
            "MX": 0.20,
        }
        return base_risk.get(country.upper(), 0.25)  # fallback: 25%

    risco = risk(country, risk1=None, risk2=None, risk3=None, risk4=None)
    expected_value = (1 - risco) * (expected_return) + risco * (-emprestimo)
    return {
        "worthy": expected_value > threshold * emprestimo,
        "expected_value profit": expected_value - emprestimo,
        "profit percentage": (expected_value - emprestimo) / emprestimo,
        "risk": risco
    }

def test12(**kwargs):
    """
    Wrapper function to run both test1 and test2 and return their results.

    Parameters:
    kwargs: Arguments for test1 and test2.
      Required: emprestimo, juros, GPA, sex, university, course, profit_threshold, country
      Optional: salary_percentage (default 0.15), max_months (default 120), max_payment_ratio (default 1.8)

    Returns:
    tuple: (result1, result2)
        result1:{
            "was_paid": bool,
            "months_left": int,
            "expected_return": float,
            "remaining_debt": float
        }
        result2:{
            "worthy": bool,
            "expected_value profit": float,
            "profit percentage": float,
            "risk": float
        }
    """
    # Get optional parameters with defaults
    salary_percentage = kwargs.get("salary_percentage", 0.15)
    max_months = kwargs.get("max_months", 120)
    max_payment_ratio = kwargs.get("max_payment_ratio", 1.8)
    
    # Run test1
    result1 = test1(
        emprestimo=kwargs["emprestimo"],
        juros=kwargs["juros"],
        GPA=kwargs["GPA"],
        sex=kwargs["sex"],
        university=kwargs["university"],
        course=kwargs["course"],
        salary_percentage=salary_percentage,
        max_months=max_months,
        max_payment_ratio=max_payment_ratio
    )

    # Run test2
    result2 = test2(
        threshold=kwargs["profit_threshold"],
        emprestimo=kwargs["emprestimo"],
        expected_return=result1["expected_return"],
        country=kwargs["country"]
    )

    return result1, result2


def get_student_by_id(student_id):
    conn = get_db_connection()
    cur = conn.cursor(dictionary=True)

    cur.execute("""
        SELECT 
            u.id, 
            CONCAT(u.nome, ' ', u.sobrenome) AS nombre_completo,
            pe.gpa, 
            pu.nome AS universidad,
            cu.nome_curso AS curso,
            cu.salario_esperado,
            cu.custo_semestre,
            pe.nacionalidade AS pais
        FROM usuarios u
        JOIN perfil_estudante pe ON u.id = pe.usuario_id
        LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
        LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
        WHERE u.id = %s AND u.categoria = 'estudante'
    """, (student_id,))

    student = cur.fetchone()
    cur.close()
    conn.close()
    return student

def find_flexible_option(GPA, sex, university, course, country, loan_amount, salary_range, months_range, threshold, salary_base):
    juros_options = [i / 100 for i in range(1, 11)]
    best_option = None
    for pct in salary_range:
        for months in months_range:
            for juros in juros_options:
                result1, result2 = test12(
                    GPA=GPA, sex=sex, university=university, course=course,
                    country=country, emprestimo=loan_amount, juros=juros,
                    salary_percentage=pct, max_months=months,
                    max_payment_ratio=8.0, profit_threshold=threshold
                )
                if result1["was_paid"] and result2["worthy"]:
                    candidate = {
                        "salary_pct": pct,
                        "duration_months": months,
                        "juros_mensal": juros,
                        "juros_anual": (1 + juros)**12 - 1,
                        "total_paid": result1["expected_return"],
                        "total_paid_ratio": result1["expected_return"] / loan_amount,
                        "profit": result2["expected_value profit"],
                        "profit_pct": result2["profit percentage"] * 100,
                        "max_payment_ratio": 8.0,
                        "loan_amount": loan_amount,
                        "months_to_pay": result1["months_left"],
                        "monthly_payment": (salary_base / 12) * pct
                    }
                    if best_option is None or ("leve" in salary_range and candidate["total_paid_ratio"] < best_option["total_paid_ratio"]) or ("intensa" in salary_range and candidate["total_paid_ratio"] < best_option["total_paid_ratio"]):
                        best_option = candidate
    return best_option

def get_recommended_loans(GPA, sex, university, course, country, loan_amount,
                           salary_base=None,
                           threshold=1.1, interest_rates=None):
    if interest_rates is None:
        interest_rates = [i / 100 for i in range(1, 11)]

    salary_options = [0.15, 0.25, 0.35]
    duration_options = list(range(120, 241, 12))
    max_ratios = [1.8, 2.0, 2.5, 3.0, 3.5, 4.0]

    all_valid_plans = []
    seen_hashes = set()

    for salary_pct in salary_options:
        for months in duration_options:
            for ratio_limit in max_ratios:
                for juros in interest_rates:
                    result1, result2 = test12(
                        GPA=GPA, sex=sex, university=university, course=course,
                        country=country, emprestimo=loan_amount, juros=juros,
                        salary_percentage=salary_pct, max_months=months,
                        max_payment_ratio=ratio_limit, profit_threshold=threshold
                    )
                    if result1["was_paid"] and result2["worthy"]:
                        monthly_payment = (salary_base / 12) * salary_pct if salary_base else None
                        unique_hash = (round(result1["expected_return"], 2), juros, salary_pct, months)
                        if unique_hash in seen_hashes:
                            continue
                        seen_hashes.add(unique_hash)
                        plan = {
                            "salary_pct": salary_pct,
                            "duration_months": months,
                            "juros_mensal": juros,
                            "juros_anual": (1 + juros)**12 - 1,
                            "total_paid": result1["expected_return"],
                            "total_paid_ratio": result1["expected_return"] / loan_amount,
                            "profit": result2["expected_value profit"],
                            "profit_pct": result2["profit percentage"] * 100,
                            "max_payment_ratio": ratio_limit,
                            "loan_amount": loan_amount,
                            "months_to_pay": result1["months_left"],
                            "monthly_payment": monthly_payment
                        }
                        all_valid_plans.append(plan)

    if not all_valid_plans:
        return []

    base = sorted(all_valid_plans, key=lambda x: abs(x["total_paid_ratio"] - 1.5))[0]
    barata = min(all_valid_plans, key=lambda x: x["total_paid_ratio"])

    leve = find_flexible_option(GPA, sex, university, course, country, loan_amount,
                                salary_range=np.arange(0.001, 0.15, 0.001),
                                months_range=range(240, 1200, 12),
                                threshold=threshold, salary_base=salary_base)

    intensa = find_flexible_option(GPA, sex, university, course, country, loan_amount,
                                   salary_range=np.arange(0.36, 1.1, 0.01),
                                   months_range=[120],
                                   threshold=threshold, salary_base=salary_base)

    final_selection = [base]
    for p in [leve, intensa, barata]:
        if p and p not in final_selection:
            final_selection.append(p)

    return final_selection

def recommend_for_student(student_id, profit_threshold=1.1, loan_amount=None):
    student = get_student_by_id(student_id)
    if not student:
        print("Estudante não encontrado.")
        return []

    GPA = float(student['gpa']) if student['gpa'] is not None else 3.0
    university = student['universidad'] if student['universidad'] else "Universidade Desconhecida"
    course = student['curso'] if student['curso'] else "Curso Desconhecido"
    salary = student['salario_esperado'] if student['salario_esperado'] else 0
    sex = "male"
    country = student.get('pais', 'BR') or 'BR'

    if loan_amount is None:
        if student['custo_semestre']:
            loan_amount = student['custo_semestre'] * 8
        else:
            print("Sem custo definido para calcular valor do curso.")
            return []

    print(f"\n🎓 Recomendação de empréstimos para {student['nombre_completo']}")
    print(f"Universidade: {university}")
    print(f"Curso: {course}")
    print(f"GPA: {GPA} | Salário esperado: {salary}")
    print(f"País considerado para risco: {country.upper()}")
    print(f"Valor total estimado do curso: {loan_amount:.2f}\n")

    options = get_recommended_loans(
        GPA=GPA, sex=sex, university=university, course=course,
        country=country, loan_amount=loan_amount, threshold=profit_threshold,
        salary_base=salary
    )

    if not options:
        print("Nenhuma opção viável encontrada para os parâmetros fornecidos.")
        return []

    labels = ["📌 Opção equilibrada", "🛋️ Opção leve", "🚀 Opção intensa", "💸 Opção econômica"]
    for i, opt in enumerate(options):
        label = labels[i] if i < len(labels) else f"Opção {i+1}"
        salary_info = f"{int(opt['salary_pct']*100)}% do salário"
        if opt['monthly_payment']:
            salary_info += f" (~US$ {opt['monthly_payment']:.2f}/mês)"
        print(f"{label}: Plano com {salary_info}, {opt['duration_months']} meses, {opt['juros_mensal']*100:.2f}% de juros mensal")
        print(f"   ✅ Quitado em {opt['duration_months'] - opt['months_to_pay']} meses | 💸 Pagamento total: US$ {opt['total_paid']:.2f} ({opt['total_paid_ratio']:.2f}x) ")
        print(f"   💰 Lucro esperado: US$ {opt['profit']:.2f} ({opt['profit_pct']:.2f}%)\n")

    return options

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("-i", "--id", type=int, required=True, help="ID do estudante")
    parser.add_argument("-t", "--threshold", type=float, default=1.1)
    parser.add_argument("-a", "--amount", type=float, help="Valor do empréstimo")
    args = parser.parse_args()

    recommend_for_student(
        student_id=args.id,
        profit_threshold=args.threshold,
        loan_amount=args.amount
    )
